<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use RuntimeException;
use Throwable;

final class AfterCommitQueueOutbox
{
    public function store(string $payload, string $queue = 'default', ?string $sourceConnection = null): int
    {
        try {
            $availableAt = $this->payloadAvailableAt($payload);

            return (int) DB::table('queue_outboxes')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'queue' => $queue,
                'payload_encrypted' => Crypt::encryptString($payload),
                'source_connection' => $sourceConnection,
                'attempts' => 0,
                'available_at' => $availableAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            try {
                $this->storeFallback($payload, $queue, $sourceConnection);
            } catch (Throwable $fallbackException) {
                throw new RuntimeException(
                    'Queue recovery journal persistence failed.',
                    previous: $fallbackException,
                );
            }

            Log::warning('Queue outbox database journal failed; local fallback was written.', [
                'exception' => $exception::class,
            ]);

            return 0;
        }
    }

    public function storeFallback(string $payload, string $queue = 'default', ?string $sourceConnection = null): string
    {
        $path = 'queue-outbox-failover/'.Str::uuid().'.payload';
        $envelope = [
            'payload' => $payload,
            'queue' => $queue,
            'source_connection' => $sourceConnection,
            'available_at' => $this->payloadAvailableAt($payload)->toIso8601String(),
            'created_at' => now()->toIso8601String(),
        ];

        $this->fallbackDisk()->put(
            $path,
            Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );

        return $path;
    }

    public function recover(?int $limit = null): int
    {
        $limit = max(1, min($limit ?? 100, 1000));
        $rows = DB::transaction(function () use ($limit): array {
            $leaseCutoff = now()->subSeconds($this->claimLeaseSeconds());
            DB::table('queue_outboxes')
                ->whereNull('completed_at')
                ->where('delivery_state', 'delivering')
                ->where(function ($query) use ($leaseCutoff): void {
                    $query->whereNull('claimed_at')
                        ->orWhere('claimed_at', '<=', $leaseCutoff);
                })
                ->update([
                    'claimed_at' => null,
                    'claim_token' => null,
                    'delivery_state' => 'pending',
                    'updated_at' => now(),
                ]);

            $rows = DB::table('queue_outboxes')
                ->whereNull('completed_at')
                ->where('delivery_state', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $claimToken = (string) Str::uuid();
                DB::table('queue_outboxes')->where('id', $row->id)->update([
                    'claimed_at' => now(),
                    'claim_token' => $claimToken,
                    'delivery_state' => 'delivering',
                    'updated_at' => now(),
                ]);
                $row->claim_token = $claimToken;
            }

            return $rows->all();
        });

        $recovered = 0;
        foreach ($rows as $row) {
            try {
                $payload = Crypt::decryptString((string) $row->payload_encrypted);
                $lastException = null;
                foreach ($this->recoveryConnections($row->source_connection) as $connection) {
                    try {
                        $this->deliverPayload(
                            $connection,
                            $payload,
                            (string) $row->queue,
                            $this->remainingDelay($row->available_at),
                        );
                        $lastException = null;
                        break;
                    } catch (Throwable $exception) {
                        $lastException = $exception;
                    }
                }
                if ($lastException !== null) {
                    throw $lastException;
                }

                $completed = DB::table('queue_outboxes')
                    ->where('id', $row->id)
                    ->where('claim_token', $row->claim_token)
                    ->where('delivery_state', 'delivering')
                    ->update([
                        'completed_at' => now(),
                        'claimed_at' => null,
                        'claim_token' => null,
                        'delivery_state' => 'completed',
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
                if ($completed !== 1) {
                    continue;
                }
                $recovered++;
            } catch (Throwable $exception) {
                $attempts = ((int) $row->attempts) + 1;
                $backoffMinutes = min(60, 2 ** min(6, $attempts));

                DB::table('queue_outboxes')
                    ->where('id', $row->id)
                    ->where('claim_token', $row->claim_token)
                    ->where('delivery_state', 'delivering')
                    ->update([
                        'attempts' => $attempts,
                        'available_at' => now()->addMinutes($backoffMinutes),
                        'claimed_at' => null,
                        'claim_token' => null,
                        'delivery_state' => 'pending',
                        'last_error' => $exception::class,
                        'updated_at' => now(),
                    ]);
            }
        }

        $recovered += $this->recoverFallback($this->recoveryConnections(), max(0, $limit - $recovered));

        return $recovered;
    }

    /** @param list<string> $connections */
    private function recoverFallback(array $connections, int $limit): int
    {
        if ($limit === 0) {
            return 0;
        }

        $recovered = 0;
        $disk = $this->fallbackDisk();
        $leaseCutoff = now()->subSeconds($this->claimLeaseSeconds());
        $candidates = [];
        foreach ($disk->files('queue-outbox-failover') as $path) {
            if (str_ends_with($path, '.reclaiming')) {
                continue;
            }

            if (str_ends_with($path, '.delivering')) {
                if ($disk->lastModified($path) <= $leaseCutoff->getTimestamp()) {
                    $candidates[] = [$path, $path.'.reclaiming', $path];
                }

                continue;
            }

            $candidates[] = [$path, $path.'.delivering', $path];
        }

        foreach ($candidates as [$path, $claimPath, $originalPath]) {
            if ($recovered >= $limit) {
                break;
            }
            try {
                if (! $disk->move($path, $claimPath)) {
                    throw new RuntimeException('Unable to claim queue fallback entry.');
                }
                $envelope = json_decode(
                    Crypt::decryptString($disk->get($claimPath)),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                if (! is_array($envelope)
                    || ! is_string($envelope['payload'] ?? null)
                    || ! is_string($envelope['queue'] ?? null)
                ) {
                    throw new RuntimeException('Invalid queue recovery envelope.');
                }
                $availableAt = is_string($envelope['available_at'] ?? null)
                    ? strtotime($envelope['available_at'])
                    : null;
                if ($availableAt !== null && $availableAt > time()) {
                    $disk->move($claimPath, $originalPath);

                    continue;
                }

                $lastException = null;
                foreach ($connections as $connection) {
                    try {
                        $this->deliverPayload(
                            $connection,
                            $envelope['payload'],
                            $envelope['queue'],
                            $availableAt !== null ? max(0, $availableAt - time()) : null,
                        );
                        $lastException = null;
                        break;
                    } catch (Throwable $exception) {
                        $lastException = $exception;
                    }
                }
                if ($lastException !== null) {
                    throw $lastException;
                }
                $disk->delete($claimPath);
                $recovered++;
            } catch (Throwable $exception) {
                if ($disk->exists($claimPath)) {
                    $disk->move($claimPath, $originalPath);
                }
                Log::warning('Queue outbox local fallback recovery failed.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        return $recovered;
    }

    /** @return list<string> */
    private function recoveryConnections(?string $sourceConnection = null): array
    {
        $configured = config('queue.connections.failover.connections', []);
        $connections = is_array($configured)
            ? array_values(array_filter($configured, static fn (mixed $connection): bool => is_string($connection) && $connection !== ''))
            : [];
        if (is_string($sourceConnection) && $sourceConnection !== '' && $sourceConnection !== 'failover') {
            array_unshift($connections, $sourceConnection);
        }

        return array_values(array_unique($connections ?: ['database']));
    }

    private function claimLeaseSeconds(): int
    {
        return max(60, (int) config('queue.outbox_claim_lease_seconds', 300));
    }

    private function deliverPayload(string $connection, string $payload, string $queue, ?int $delayOverride = null): mixed
    {
        $decoded = json_decode($payload, true);
        $delay = $delayOverride ?? (is_array($decoded) ? ($decoded['delay'] ?? null) : null);
        if ($delay === null || $delay === 0) {
            return Queue::connection($connection)->pushRaw($payload, $queue);
        }
        if (! is_int($delay) && (! is_string($delay) || ! ctype_digit($delay))) {
            throw new RuntimeException('Invalid delayed queue payload.');
        }
        $delay = (int) $delay;

        $queueConnection = Queue::connection($connection);
        if (method_exists($queueConnection, 'pushToDatabase')) {
            $method = new ReflectionMethod($queueConnection, 'pushToDatabase');
            $method->setAccessible(true);

            return $method->invoke($queueConnection, $queue, $payload, $delay, 0);
        }
        if (method_exists($queueConnection, 'laterRaw')) {
            $method = new ReflectionMethod($queueConnection, 'laterRaw');
            $method->setAccessible(true);

            return $method->invoke($queueConnection, $delay, $payload, $queue);
        }

        throw new RuntimeException('Queue backend cannot preserve delayed raw payloads.');
    }

    private function payloadAvailableAt(string $payload): Carbon
    {
        $decoded = json_decode($payload, true);
        $delay = is_array($decoded) ? ($decoded['delay'] ?? 0) : 0;
        if ($delay === 0) {
            return now();
        }
        if (! is_int($delay) && (! is_string($delay) || ! ctype_digit($delay))) {
            throw new RuntimeException('Invalid delayed queue payload.');
        }

        return now()->addSeconds(max(0, (int) $delay));
    }

    private function remainingDelay(mixed $availableAt): ?int
    {
        if ($availableAt === null) {
            return null;
        }

        $timestamp = is_object($availableAt) && method_exists($availableAt, 'getTimestamp')
            ? $availableAt->getTimestamp()
            : strtotime((string) $availableAt);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function fallbackDisk()
    {
        if (! app()->environment('testing') && ! (bool) config('queue.outbox_fallback_shared', false)) {
            throw new RuntimeException('A shared queue outbox fallback disk is required outside testing.');
        }

        return Storage::disk((string) config('queue.outbox_fallback_disk', 'local'));
    }
}

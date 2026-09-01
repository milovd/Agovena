<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\FailoverQueue;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AfterCommitFailoverQueue extends FailoverQueue
{
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        array $connections,
    ) {
        parent::__construct($manager, $events, $connections);
        $this->dispatchAfterCommit = true;
    }

    public function push($job, $data = '', $queue = null)
    {
        $queueName = $this->queueName($queue);
        $payload = $this->createPayload($job, $queueName, $data);

        try {
            return $this->enqueueUsing(
                $job,
                $payload,
                $queueName,
                null,
                function ($payload, $queue) use ($job): mixed {
                    try {
                        return $this->attemptOnAllConnections('pushRaw', [$payload, $queue, []], $job);
                    } catch (Throwable $exception) {
                        $this->journal((string) $payload, $queue);

                        throw $exception;
                    }
                },
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queueName = $this->queueName($queue);

        try {
            return $this->enqueueUsing(
                (string) $payload,
                (string) $payload,
                $queueName,
                null,
                function ($payload, $queue) {
                    try {
                        return $this->attemptOnAllConnections('pushRaw', [$payload, $queue, []]);
                    } catch (Throwable $exception) {
                        $this->journal((string) $payload, $queue);

                        throw $exception;
                    }
                },
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        $queueName = $this->queueName($queue);
        $payload = $this->createPayload($job, $queueName, $data, $delay);

        try {
            return $this->enqueueUsing(
                $job,
                $payload,
                $queueName,
                $delay,
                function () use ($delay, $job, $data, $queueName, $payload): mixed {
                    try {
                        return $this->attemptOnAllConnections('later', [$delay, $job, $data, $queueName], $job);
                    } catch (Throwable $exception) {
                        $this->journal((string) $payload, $queueName);

                        throw $exception;
                    }
                },
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    private function journal(string $payload, $queue): void
    {
        try {
            app(AfterCommitQueueOutbox::class)->store(
                $payload,
                $this->queueName($queue),
                $this->getConnectionName(),
            );
        } catch (Throwable $journalException) {
            Log::critical('Queue recovery journal persistence failed.', [
                'exception' => $journalException::class,
            ]);

            throw new RuntimeException('Queue recovery journal persistence failed.', previous: $journalException);
        }
    }

    private function queueName($queue): string
    {
        if ($queue instanceof \BackedEnum) {
            return (string) $queue->value;
        }

        if ($queue instanceof \UnitEnum) {
            return $queue->name;
        }

        return is_string($queue) && $queue !== ''
            ? $queue
            : (string) config('queue.connections.failover.queue', 'default');
    }
}

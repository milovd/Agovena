<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Paddle\PaddleConnectionChecker;
use Agovena\Extensions\Paddle\PaddleProviderException;

final class FakePaddleApi implements PaddleApi, PaddleConnectionChecker
{
    public int $transactionCalls = 0;

    public int $pingCalls = 0;

    public bool $pingFails = false;

    public function ping(): void
    {
        $this->pingCalls++;
        if ($this->pingFails) {
            throw PaddleProviderException::failed('paddle::messages.errors.request_failed');
        }
    }

    /** @var array<string, mixed> */
    public array $transaction = [
        'id' => 'txn_test',
        'status' => 'draft',
        'checkout' => ['url' => 'https://checkout.paddle.test/txn_test'],
    ];

    public function createTransaction(array $payload, ?string $idempotencyKey = null): array
    {
        $this->transactionCalls++;

        return $this->transaction;
    }

    public function getTransaction(string $transactionId): array
    {
        return array_merge($this->transaction, ['id' => $transactionId]);
    }

    /** @var array<string, mixed>|null */
    public ?array $adjustment = null;

    public function createAdjustment(string $transactionId, string $reason, string $type = 'full', ?string $idempotencyKey = null): array
    {
        return $this->adjustment ?? ['id' => 'adj_test', 'transaction_id' => $transactionId, 'type' => $type, 'reason' => $reason];
    }
}

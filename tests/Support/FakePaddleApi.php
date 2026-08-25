<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Paddle\PaddleApi;

final class FakePaddleApi implements PaddleApi
{
    public int $transactionCalls = 0;

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

    public function createAdjustment(string $transactionId, string $reason, string $type = 'full', ?string $idempotencyKey = null): array
    {
        return ['id' => 'adj_test', 'transaction_id' => $transactionId, 'type' => $type, 'reason' => $reason];
    }
}

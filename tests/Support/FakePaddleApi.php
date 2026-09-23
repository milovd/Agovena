<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Paddle\PaddleConnectionChecker;
use Agovena\Extensions\Paddle\PaddleProviderException;

final class FakePaddleApi implements PaddleApi, PaddleConnectionChecker
{
    public int $transactionCalls = 0;

    public ?PaddleProviderException $createException = null;

    /** @var array<string, mixed>|null */
    public ?array $transactionPayload = null;

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
        $this->transactionPayload = $payload;

        if ($this->createException !== null) {
            throw $this->createException;
        }

        return $this->transaction;
    }

    /** @var array<string, mixed>|null */
    public ?array $previewPayload = null;

    /** @var array<string, mixed> */
    public array $preview = [
        'available_payment_methods' => ['card', 'ideal'],
    ];

    public function previewTransaction(array $payload): array
    {
        $this->previewPayload = $payload;

        return $this->preview;
    }

    public function getTransaction(string $transactionId): array
    {
        return array_merge($this->transaction, ['id' => $transactionId]);
    }

    /** @var array<string, mixed> */
    public array $subscription = [
        'id' => 'sub_test',
        'status' => 'active',
    ];

    public int $subscriptionCalls = 0;

    public function getSubscription(string $subscriptionId): array
    {
        $this->subscriptionCalls++;

        return array_merge($this->subscription, ['id' => $subscriptionId]);
    }

    public ?array $lastSubscriptionAction = null;

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        $this->lastSubscriptionAction = [
            'action' => 'cancel',
            'id' => $subscriptionId,
            'at_period_end' => $atPeriodEnd,
        ];

        return array_merge($this->subscription, ['id' => $subscriptionId, 'status' => $atPeriodEnd ? 'active' : 'canceled']);
    }

    public function clearScheduledSubscriptionChange(string $subscriptionId): array
    {
        $this->lastSubscriptionAction = [
            'action' => 'clear_scheduled_change',
            'id' => $subscriptionId,
        ];

        return array_merge($this->subscription, ['id' => $subscriptionId, 'status' => 'active']);
    }

    /** @var array<string, mixed>|null */
    public ?array $adjustment = null;

    /** @var array<string, mixed>|null */
    public ?array $lastAdjustmentRequest = null;

    /**
     * @param  list<array{item_id: string, type: string, amount?: string}>|null  $items
     */
    public function createAdjustment(
        string $transactionId,
        string $reason,
        string $type = 'full',
        ?array $items = null,
        ?string $idempotencyKey = null,
    ): array {
        $this->lastAdjustmentRequest = [
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'type' => $type,
            'items' => $items,
            'idempotency_key' => $idempotencyKey,
        ];

        return $this->adjustment ?? [
            'id' => 'adj_test',
            'transaction_id' => $transactionId,
            'type' => $type,
            'items' => $items,
            'reason' => $reason,
        ];
    }
}

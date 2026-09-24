<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Tebex\TebexApi;
use Agovena\Extensions\Tebex\TebexConnectionChecker;
use Agovena\Extensions\Tebex\TebexProviderException;

final class FakeTebexApi implements TebexApi, TebexConnectionChecker
{
    public int $checkoutCalls = 0;

    /** @var list<array<string, mixed>> */
    public array $checkoutPayloads = [];

    /** @var list<string|null> */
    public array $checkoutIdempotencyKeys = [];

    public int $pingCalls = 0;

    public bool $pingFails = false;

    public function ping(): void
    {
        $this->pingCalls++;
        if ($this->pingFails) {
            throw TebexProviderException::failed('tebex::messages.errors.request_failed');
        }
    }

    public ?string $throwOn = null;

    public function createCheckout(array $payload, ?string $idempotencyKey = null): array
    {
        $this->checkoutCalls++;
        $this->checkoutPayloads[] = $payload;
        $this->checkoutIdempotencyKeys[] = $idempotencyKey;
        if ($this->throwOn === 'create_checkout') {
            throw new TebexProviderException('tebex::messages.errors.request_failed', null, true);
        }

        return [
            'id' => 'checkout-test',
            'ident' => 'basket-ident',
            'links' => ['checkout' => 'https://checkout.tebex.test/basket-ident'],
        ];
    }

    public function getPayment(string $transactionId): array
    {
        return array_merge(['transaction_id' => $transactionId, 'status' => ['id' => 1]], $this->payment);
    }

    /** @var array<string, mixed> */
    public array $payment = [];

    /** @var array<string, mixed>|null */
    public ?array $refund = null;

    /** @var list<string|null> */
    public array $refundIdempotencyKeys = [];

    /** @var list<array<string, mixed>> */
    public array $recurringActions = [];

    /** @var array<string, mixed> */
    public array $recurringPayment = [
        'reference' => 'tbx-r-test',
        'status' => ['id' => 2, 'description' => 'Active'],
    ];

    public function refundPayment(string $transactionId, ?string $reason = null, ?string $idempotencyKey = null): array
    {
        $this->refundIdempotencyKeys[] = $idempotencyKey;
        if ($this->throwOn === 'refund') {
            throw new TebexProviderException('tebex::messages.errors.request_failed', null, true);
        }

        return $this->refund ?? ['id' => 'refund-test', 'transaction_id' => $transactionId];
    }

    public function getRecurringPayment(string $reference): array
    {
        return array_merge($this->recurringPayment, ['reference' => $reference]);
    }

    public function cancelRecurringPayment(string $reference): array
    {
        $this->recurringActions[] = ['action' => 'cancel', 'reference' => $reference];
        $this->recurringPayment['status'] = ['id' => 5, 'description' => 'Cancelled'];

        return array_merge($this->recurringPayment, ['reference' => $reference]);
    }

    public function updateRecurringPaymentStatus(string $reference, string $status, ?string $pausedUntil = null): array
    {
        $this->recurringActions[] = array_filter([
            'action' => 'status',
            'reference' => $reference,
            'status' => $status,
            'paused_until' => $pausedUntil,
        ], static fn (mixed $value): bool => $value !== null);

        return array_merge($this->recurringPayment, ['reference' => $reference]);
    }
}

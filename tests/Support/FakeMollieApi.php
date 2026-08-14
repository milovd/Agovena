<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Mollie\MollieApi;
use Agovena\Extensions\Mollie\MollieProviderException;

final class FakeMollieApi implements MollieApi
{
    /** @var array<string, array<string, mixed>> */
    public array $payments = [];

    /** @var array<string, string> */
    public array $idempotency = [];

    /** @var list<array{id: string, description: string}> */
    public array $methods = [
        ['id' => 'ideal', 'description' => 'iDEAL'],
        ['id' => 'bancontact', 'description' => 'Bancontact'],
        ['id' => 'creditcard', 'description' => 'Card'],
        ['id' => 'paypal', 'description' => 'PayPal'],
    ];

    public int $createCalls = 0;

    public int $refundCalls = 0;

    public bool $failCreate = false;

    public bool $timeout = false;

    public bool $malformed = false;

    public bool $unauthorized = false;

    public bool $serverError = false;

    public bool $failGet = false;

    public bool $failRefund = false;

    public bool $failCancel = false;

    public string $nextStatus = 'open';

    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        if ($this->timeout) {
            throw MollieProviderException::failed('mollie::messages.health.unreachable');
        }
        if ($this->unauthorized) {
            throw MollieProviderException::failed('mollie::messages.errors.unauthorized');
        }
        if ($this->serverError) {
            throw MollieProviderException::failed('mollie::messages.errors.server_error');
        }
        if ($this->failCreate) {
            throw MollieProviderException::failed('mollie::messages.errors.create_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency[$idempotencyKey])) {
            return $this->payments[$this->idempotency[$idempotencyKey]];
        }

        $this->createCalls++;
        $id = 'tr_fake_'.($this->createCalls);
        $payment = [
            'id' => $id,
            'status' => $this->nextStatus,
            'mode' => 'test',
            'checkout_url' => $this->malformed ? null : 'https://mollie.test/checkout/'.$id,
            'is_cancelable' => true,
            'sequence_type' => $payload['sequenceType'] ?? 'oneoff',
            'customer_id' => $payload['customerId'] ?? 'cst_fake',
            'mandate_id' => ($payload['sequenceType'] ?? null) === 'recurring' ? 'mdt_fake' : null,
            'amount' => $payload['amount'] ?? ['value' => '10.00', 'currency' => 'EUR'],
            'amount_refunded' => null,
            'metadata' => $payload['metadata'] ?? [],
        ];
        if ($this->malformed) {
            $payment['id'] = $id;
        }
        $this->payments[$id] = $payment;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency[$idempotencyKey] = $id;
        }

        return $payment;
    }

    public function getPayment(string $id): array
    {
        if ($this->failGet) {
            throw MollieProviderException::failed('mollie::messages.health.unreachable');
        }
        if (! isset($this->payments[$id])) {
            throw MollieProviderException::failed('mollie::messages.errors.create_failed');
        }

        return $this->payments[$id];
    }

    public function cancelPayment(string $id): array
    {
        if ($this->failCancel) {
            throw MollieProviderException::failed('mollie::messages.errors.cancel_unsupported');
        }
        $payment = $this->getPayment($id);
        if (! ($payment['is_cancelable'] ?? false)) {
            throw MollieProviderException::failed('mollie::messages.errors.cancel_unsupported');
        }
        $payment['status'] = 'canceled';
        $this->payments[$id] = $payment;

        return $payment;
    }

    public function refundPayment(string $paymentId, array $payload, ?string $idempotencyKey = null): array
    {
        if ($this->failRefund) {
            throw MollieProviderException::failed('mollie::messages.errors.refund_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency['refund:'.$idempotencyKey])) {
            return ['id' => $this->idempotency['refund:'.$idempotencyKey], 'status' => 'refunded', 'payment_id' => $paymentId];
        }

        $this->refundCalls++;
        $refundId = 're_fake_'.$this->refundCalls;
        $payment = $this->getPayment($paymentId);
        $payment['amount_refunded'] = $payload['amount'] ?? $payment['amount'];
        if (($payload['amount']['value'] ?? null) === ($payment['amount']['value'] ?? null)) {
            $payment['status'] = 'refunded';
        }
        $this->payments[$paymentId] = $payment;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency['refund:'.$idempotencyKey] = $refundId;
        }

        return ['id' => $refundId, 'status' => 'refunded', 'payment_id' => $paymentId];
    }

    public function listEnabledMethods(): array
    {
        if ($this->timeout) {
            throw MollieProviderException::failed('mollie::messages.health.unreachable');
        }

        return $this->methods;
    }

    public function createCustomer(array $payload): array
    {
        return [
            'id' => 'cst_fake',
            'name' => (string) ($payload['name'] ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
        ];
    }

    public function markPaid(string $id): void
    {
        $this->payments[$id]['status'] = 'paid';
        if (($this->payments[$id]['mandate_id'] ?? null) === null) {
            $this->payments[$id]['mandate_id'] = 'mdt_fake';
        }
    }

    public function markStatus(string $id, string $status): void
    {
        $this->payments[$id]['status'] = $status;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\PayPal\PayPalApi;
use Agovena\Extensions\PayPal\PayPalProviderException;

final class FakePayPalApi implements PayPalApi
{
    /** @var array<string, array<string, mixed>> */
    public array $orders = [];

    /** @var array<string, string> */
    public array $idempotency = [];

    public int $createCalls = 0;

    public int $refundCalls = 0;

    public int $pingCalls = 0;

    public bool $failCreate = false;

    public bool $unauthorized = false;

    public bool $unreachable = false;

    public bool $malformed = false;

    public bool $failRefund = false;

    public bool $verifyWebhook = true;

    public function createOrder(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->failCreate) {
            throw PayPalProviderException::failed('paypal::messages.errors.create_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency[$idempotencyKey])) {
            return $this->orders[$this->idempotency[$idempotencyKey]];
        }

        $this->createCalls++;
        $id = '5O110127'.$this->createCalls;
        $order = [
            'id' => $id,
            'status' => 'CREATED',
            'links' => $this->malformed ? [] : [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token='.$id,
            ]],
            'purchase_units' => $payload['purchase_units'] ?? [],
        ];
        $this->orders[$id] = $order;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency[$idempotencyKey] = $id;
        }

        return $order;
    }

    public function getOrder(string $id): array
    {
        $this->guard();
        if (! isset($this->orders[$id])) {
            throw PayPalProviderException::failed('paypal::messages.errors.provider_failed');
        }

        return $this->orders[$id];
    }

    public function captureOrder(string $id, ?string $idempotencyKey = null): array
    {
        $this->guard();
        unset($idempotencyKey);
        $order = $this->getOrder($id);
        $order['status'] = 'COMPLETED';
        $order['purchase_units'][0]['payments']['captures'][0] = [
            'id' => 'CAPTURE_'.$id,
            'status' => 'COMPLETED',
        ];
        $this->orders[$id] = $order;

        return $order;
    }

    public function refundCapture(string $captureId, array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        unset($payload, $idempotencyKey);
        $this->refundCalls++;
        if ($this->failRefund) {
            throw PayPalProviderException::failed('paypal::messages.errors.refund_failed');
        }

        return ['id' => 'REFUND_'.$captureId, 'status' => 'COMPLETED'];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $this->guard();
        unset($payload);

        return $this->verifyWebhook;
    }

    public function ping(): void
    {
        $this->guard();
        $this->pingCalls++;
    }

    private function guard(): void
    {
        if ($this->unauthorized) {
            throw PayPalProviderException::failed('paypal::messages.errors.unauthorized', 401);
        }
        if ($this->unreachable) {
            throw PayPalProviderException::failed('paypal::messages.health.unreachable');
        }
    }
}

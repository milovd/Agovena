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

    /** @var list<array<string, mixed>> */
    public array $orderPayloads = [];

    public int $createCalls = 0;

    public int $refundCalls = 0;

    public int $saleRefundCalls = 0;

    public int $captureCalls = 0;

    public int $deletePaymentTokenCalls = 0;

    /** @var list<string|null> */
    public array $captureIdempotencyKeys = [];

    /** @var list<string> */
    public array $deletedPaymentTokenIds = [];

    public int $pingCalls = 0;

    public bool $failCreate = false;

    public bool $unknownCreate = false;

    public bool $unauthorized = false;

    public bool $unreachable = false;

    public bool $malformed = false;

    public bool $failRefund = false;

    public bool $unknownRefund = false;

    public bool $malformedRefund = false;

    public bool $verifyWebhook = true;

    public function createOrder(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->failCreate) {
            throw PayPalProviderException::failed('paypal::messages.errors.create_failed');
        }
        if ($this->unknownCreate) {
            throw PayPalProviderException::unknown('paypal::messages.health.unreachable');
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
        $this->orderPayloads[$id] = $payload;
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
        $this->captureCalls++;
        $this->captureIdempotencyKeys[] = $idempotencyKey;
        $order = $this->getOrder($id);
        $order['status'] = 'COMPLETED';
        $order['purchase_units'][0]['payments']['captures'][0] = [
            'id' => 'CAPTURE_'.$id,
            'status' => 'COMPLETED',
        ];

        $paypal = $this->orderPayloads[$id]['payment_source']['paypal'] ?? null;
        if (is_array($paypal) && isset($paypal['attributes']['vault'])) {
            $merchantCustomerId = $paypal['attributes']['customer']['merchant_customer_id'] ?? null;
            $order['payment_source']['paypal']['attributes']['vault'] = [
                'id' => 'VAULT_'.$id,
                'status' => 'VAULTED',
                'customer' => [
                    'id' => 'PPCUST_'.$id,
                ],
            ];
            $order['payment_source']['paypal']['attributes']['customer'] = [
                'merchant_customer_id' => $merchantCustomerId,
            ];
        }

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
        if ($this->unknownRefund) {
            throw PayPalProviderException::unknown('paypal::messages.health.unreachable');
        }

        return ['id' => $this->malformedRefund ? '' : 'REFUND_'.$captureId, 'status' => 'COMPLETED'];
    }

    public function refundSale(string $saleId, array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        unset($payload, $idempotencyKey);
        $this->saleRefundCalls++;
        if ($this->failRefund) {
            throw PayPalProviderException::failed('paypal::messages.errors.refund_failed');
        }
        if ($this->unknownRefund) {
            throw PayPalProviderException::unknown('paypal::messages.health.unreachable');
        }

        return ['id' => $this->malformedRefund ? '' : 'REFUND_'.$saleId, 'state' => 'completed'];
    }

    public function deletePaymentToken(string $paymentTokenId): array
    {
        $this->guard();
        $this->deletePaymentTokenCalls++;
        $this->deletedPaymentTokenIds[] = $paymentTokenId;

        return [];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $this->guard();
        unset($payload);

        return $this->verifyWebhook;
    }

    public function ping(): void
    {
        $this->pingCalls++;
        $this->guard();
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

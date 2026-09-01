<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Stripe\StripeApi;
use Agovena\Extensions\Stripe\StripeProviderException;
use Stripe\Webhook;

final class FakeStripeApi implements StripeApi
{
    /** @var array<string, array<string, mixed>> */
    public array $sessions = [];

    /** @var array<string, array<string, mixed>> */
    public array $intents = [];

    /** @var array<string, string> */
    public array $idempotency = [];

    public int $checkoutCalls = 0;

    public int $intentCalls = 0;

    public int $refundCalls = 0;

    public int $balanceCalls = 0;

    public bool $failCreate = false;

    public bool $timeout = false;

    public bool $unknownOutcome = false;

    public bool $malformed = false;

    public bool $failGet = false;

    public bool $failRefund = false;

    public bool $malformedRefund = false;

    public bool $failCancel = false;

    public bool $unauthorized = false;

    public bool $serverError = false;

    public string $nextIntentStatus = 'requires_payment_method';

    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->failCreate) {
            throw StripeProviderException::failed('stripe::messages.errors.create_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency[$idempotencyKey])) {
            return $this->sessions[$this->idempotency[$idempotencyKey]];
        }

        $this->checkoutCalls++;
        $sessionId = 'cs_test_'.$this->checkoutCalls;
        $intentId = 'pi_test_'.$this->checkoutCalls;
        $intent = [
            'id' => $intentId,
            'status' => $this->nextIntentStatus,
            'amount' => (int) ($payload['line_items'][0]['price_data']['unit_amount'] ?? 0),
            'amount_received' => 0,
            'amount_refunded' => 0,
            'currency' => (string) ($payload['line_items'][0]['price_data']['currency'] ?? 'eur'),
            'customer' => $payload['customer'] ?? 'cus_test',
            'payment_method' => null,
            'metadata' => $payload['payment_intent_data']['metadata'] ?? [],
        ];
        $session = [
            'id' => $sessionId,
            'url' => $this->malformed ? null : 'https://checkout.stripe.test/c/pay/'.$sessionId,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'payment_intent' => $intentId,
            'customer' => $intent['customer'],
            'customer_email' => $payload['customer_email'] ?? null,
            'customer_details' => [
                'email' => $payload['customer_email'] ?? 'stripe-buyer@example.test',
            ],
            'metadata' => $payload['metadata'] ?? [],
        ];
        $this->intents[$intentId] = $intent;
        $this->sessions[$sessionId] = $session;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency[$idempotencyKey] = $sessionId;
        }

        return $session;
    }

    public function retrieveCheckoutSession(string $id): array
    {
        $this->guard();
        if ($this->failGet || ! isset($this->sessions[$id])) {
            throw StripeProviderException::failed('stripe::messages.errors.sync_failed');
        }

        return $this->sessions[$id];
    }

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->failCreate) {
            throw StripeProviderException::failed('stripe::messages.errors.create_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency['pi-'.$idempotencyKey])) {
            return $this->intents[$this->idempotency['pi-'.$idempotencyKey]];
        }

        $this->intentCalls++;
        $id = 'pi_recurring_'.$this->intentCalls;
        $intent = [
            'id' => $id,
            'status' => $this->nextIntentStatus,
            'amount' => (int) ($payload['amount'] ?? 0),
            'amount_received' => $this->nextIntentStatus === 'succeeded' ? (int) ($payload['amount'] ?? 0) : 0,
            'amount_refunded' => 0,
            'currency' => (string) ($payload['currency'] ?? 'eur'),
            'customer' => $payload['customer'] ?? 'cus_test',
            'payment_method' => $payload['payment_method'] ?? 'pm_test',
            'metadata' => $payload['metadata'] ?? [],
        ];
        $this->intents[$id] = $intent;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency['pi-'.$idempotencyKey] = $id;
        }

        return $intent;
    }

    public function retrievePaymentIntent(string $id): array
    {
        $this->guard();
        if ($this->failGet || ! isset($this->intents[$id])) {
            throw StripeProviderException::failed('stripe::messages.errors.sync_failed');
        }

        return $this->intents[$id];
    }

    public function cancelPaymentIntent(string $id): array
    {
        $this->guard();
        if ($this->failCancel || ! isset($this->intents[$id])) {
            throw StripeProviderException::failed('stripe::messages.errors.cancel_unsupported');
        }
        $this->intents[$id]['status'] = 'canceled';

        return $this->intents[$id];
    }

    public function refundPaymentIntent(string $paymentIntentId, array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->failRefund || ! isset($this->intents[$paymentIntentId])) {
            throw StripeProviderException::failed('stripe::messages.errors.refund_failed');
        }
        $this->refundCalls++;
        $amount = (int) ($payload['amount'] ?? $this->intents[$paymentIntentId]['amount']);
        $this->intents[$paymentIntentId]['amount_refunded'] = (int) ($this->intents[$paymentIntentId]['amount_refunded'] ?? 0) + $amount;
        $latest = is_array($this->intents[$paymentIntentId]['latest_charge'] ?? null)
            ? $this->intents[$paymentIntentId]['latest_charge']
            : ['id' => 'ch_test', 'amount' => $this->intents[$paymentIntentId]['amount'], 'amount_refunded' => 0];
        $latest['amount_refunded'] = $this->intents[$paymentIntentId]['amount_refunded'];
        $this->intents[$paymentIntentId]['latest_charge'] = $latest;

        return [
            'id' => $this->malformedRefund ? '' : 're_test_'.$this->refundCalls,
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'status' => 'succeeded',
        ];
    }

    public function retrieveBalance(): array
    {
        $this->guard();
        $this->balanceCalls++;

        return ['object' => 'balance', 'livemode' => false];
    }

    public function constructEvent(string $payload, string $signature, string $secret): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable) {
            throw StripeProviderException::failed('stripe::messages.errors.webhook_invalid');
        }

        /** @var array<string, mixed> $array */
        $array = $event->toArray();

        return $array;
    }

    public function markPaid(string $externalId): void
    {
        $intentId = $this->resolveIntentId($externalId);
        if ($intentId === null) {
            return;
        }
        $this->intents[$intentId]['status'] = 'succeeded';
        $this->intents[$intentId]['amount_received'] = (int) ($this->intents[$intentId]['amount'] ?? 0);
        $this->intents[$intentId]['payment_method'] = 'pm_test';
        foreach ($this->sessions as $id => $session) {
            if (($session['payment_intent'] ?? null) === $intentId || $id === $externalId) {
                $this->sessions[$id]['status'] = 'complete';
                $this->sessions[$id]['payment_status'] = 'paid';
                $this->sessions[$id]['payment_method'] = 'pm_test';
            }
        }
    }

    public function markStatus(string $externalId, string $status): void
    {
        $intentId = $this->resolveIntentId($externalId);
        if ($intentId === null) {
            return;
        }
        $this->intents[$intentId]['status'] = $status;
    }

    public function sessionForIntent(string $intentId): ?array
    {
        foreach ($this->sessions as $session) {
            if (($session['payment_intent'] ?? null) === $intentId) {
                return $session;
            }
        }

        return null;
    }

    private function resolveIntentId(string $externalId): ?string
    {
        if (isset($this->intents[$externalId])) {
            return $externalId;
        }
        if (isset($this->sessions[$externalId]['payment_intent']) && is_string($this->sessions[$externalId]['payment_intent'])) {
            return $this->sessions[$externalId]['payment_intent'];
        }

        return null;
    }

    private function guard(): void
    {
        if ($this->unknownOutcome) {
            throw StripeProviderException::unknown('stripe::messages.health.unreachable');
        }
        if ($this->timeout) {
            throw StripeProviderException::failed('stripe::messages.health.unreachable');
        }
        if ($this->unauthorized) {
            throw StripeProviderException::failed('stripe::messages.errors.unauthorized');
        }
        if ($this->serverError) {
            throw StripeProviderException::failed('stripe::messages.errors.server_error');
        }
    }
}

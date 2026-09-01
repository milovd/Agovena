<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Contracts\ValidatesWebhookPayload;
use App\Agovena\Security\SensitiveDataRedactor;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Secure webhook ingress: verify → persist idempotently → apply normalized status.
 */
final class HandlePaymentWebhook
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ApplyNormalizedPaymentStatus $applyStatus,
        private readonly PaymentLifecycleLock $lifecycleLock,
    ) {}

    public function handle(string $gatewayId, Request $request): WebhookHandleResult
    {
        $gateway = $this->gateways->get($gatewayId);
        if ($gateway === null || ! $gateway->capabilities()->webhooks) {
            throw new AccessDeniedHttpException('Unknown or webhook-incapable gateway.');
        }

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('payment.webhook.verification_failed', [
                'gateway_id' => $gatewayId,
            ]);
            throw new AccessDeniedHttpException('Webhook verification failed.');
        }

        $payload = $gateway->parseWebhook($request);
        $externalEventId = $payload->externalEventId;
        if ($externalEventId === '') {
            $externalEventId = null;
        }

        $event = $this->findOrCreateEvent($gatewayId, $externalEventId, $payload->externalPaymentId, $payload->status->value, $payload->raw);

        return $this->processEvent($event, $payload, $gatewayId, $gateway);
    }

    public function reconcileDeferred(PaymentWebhookEvent $event): WebhookHandleResult
    {
        $gateway = $this->gateways->get($event->gateway_id);
        if ($gateway === null || ! $gateway->capabilities()->webhooks) {
            throw new AccessDeniedHttpException('Unknown or webhook-incapable gateway.');
        }

        $status = PaymentStatus::tryFrom($event->status);
        if ($status === null) {
            throw new InvalidArgumentException('Stored webhook status is invalid.');
        }

        return $this->processEvent(
            $event,
            new WebhookPayload(
                externalEventId: $event->external_event_id,
                externalPaymentId: $event->external_payment_id,
                status: $status,
                raw: is_array($event->payload) ? $event->payload : [],
            ),
            $event->gateway_id,
            $gateway,
        );
    }

    private function processEvent(
        PaymentWebhookEvent $event,
        WebhookPayload $payload,
        string $gatewayId,
        PaymentGateway $gateway,
    ): WebhookHandleResult {
        $process = function () use ($event, $payload, $gatewayId, $gateway): array {
            return DB::transaction(function () use ($event, $payload, $gatewayId, $gateway): array {
                /** @var PaymentWebhookEvent $locked */
                $locked = PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                if (in_array($locked->processing_status, ['processed', 'ignored'], true)) {
                    if ($locked->processing_status === 'processed'
                        && $locked->status === PaymentStatus::Paid->value
                        && $locked->external_payment_id !== null
                    ) {
                        $attempt = PaymentAttempt::query()
                            ->where('gateway_id', $gatewayId)
                            ->where('external_id', $locked->external_payment_id)
                            ->first();

                        if ($attempt !== null) {
                            $this->applyStatus->handle($attempt, PaymentStatus::Paid);
                        }
                    }

                    return ['event' => $locked, 'duplicate' => true];
                }

                if ($payload->externalPaymentId === null || $payload->externalPaymentId === '') {
                    $locked->processing_status = 'deferred';
                    $locked->processed_at = null;
                    $locked->save();
                    Log::warning('payment.webhook.deferred_missing_payment_id', [
                        'gateway_id' => $gatewayId,
                        'webhook_event_id' => $locked->id,
                    ]);

                    return ['event' => $locked->fresh() ?? $locked, 'duplicate' => false];
                }

                $blocked = false;
                $attempt = PaymentAttempt::query()
                    ->where('gateway_id', $gatewayId)
                    ->where('external_id', $payload->externalPaymentId)
                    ->first();

                if ($attempt !== null && $gateway instanceof ValidatesWebhookPayload && ! $gateway->validateWebhookPayload($attempt, $payload)) {
                    $locked->processing_status = 'ignored';
                    $locked->processed_at = now();
                    $locked->save();
                    Log::warning('payment.webhook.payload_mismatch', [
                        'gateway_id' => $gatewayId,
                        'external_payment_id' => $payload->externalPaymentId,
                        'webhook_event_id' => $locked->id,
                    ]);

                    return ['event' => $locked->fresh() ?? $locked, 'duplicate' => false];
                }

                if ($attempt === null) {
                    $locked->processing_status = 'deferred';
                    $locked->save();
                    Log::warning('payment.webhook.deferred_missing_attempt', [
                        'gateway_id' => $gatewayId,
                        'external_payment_id' => $payload->externalPaymentId,
                        'webhook_event_id' => $locked->id,
                    ]);

                    return ['event' => $locked->fresh() ?? $locked, 'duplicate' => false];
                }

                if ($attempt !== null) {
                    $result = $this->applyStatus->handle($attempt, $payload->status);
                    $blocked = $result->blockedByTerminalState;
                }

                if ($blocked) {
                    $payment = Payment::query()->whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();
                    $payment->reconciliation_status = 'manual_review';
                    $payment->reconciliation_meta = array_merge(
                        is_array($payment->reconciliation_meta) ? $payment->reconciliation_meta : [],
                        [
                            'reason' => 'paid_webhook_after_terminal_order',
                            'gateway_id' => $gatewayId,
                            'external_payment_id' => $payload->externalPaymentId,
                            'webhook_event_id' => $locked->id,
                            'recorded_at' => now()->toIso8601String(),
                        ],
                    );
                    $payment->save();
                    $locked->processing_status = 'ignored';
                    $locked->processed_at = now();
                    $locked->save();
                    Log::warning('payment.webhook.ignored_terminal_order', [
                        'gateway_id' => $gatewayId,
                        'external_payment_id' => $payload->externalPaymentId,
                        'webhook_event_id' => $locked->id,
                    ]);

                    return ['event' => $locked->fresh() ?? $locked, 'duplicate' => false];
                }

                $locked->processing_status = 'processed';
                $locked->processed_at = now();
                $locked->save();

                return ['event' => $locked->fresh() ?? $locked, 'duplicate' => false];
            });
        };

        $attempt = $payload->externalPaymentId === null || $payload->externalPaymentId === ''
            ? null
            : PaymentAttempt::query()
                ->where('gateway_id', $gatewayId)
                ->where('external_id', $payload->externalPaymentId)
                ->first();

        $processed = $attempt === null
            ? $process()
            : $this->lifecycleLock->run($attempt->order_id, $process);

        return new WebhookHandleResult($processed['event'], duplicate: $processed['duplicate']);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function findOrCreateEvent(
        string $gatewayId,
        ?string $externalEventId,
        ?string $externalPaymentId,
        string $status,
        array $raw,
    ): PaymentWebhookEvent {
        if ($externalEventId !== null) {
            $existing = PaymentWebhookEvent::query()
                ->where('gateway_id', $gatewayId)
                ->where('external_event_id', $externalEventId)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return PaymentWebhookEvent::query()->create([
                'gateway_id' => $gatewayId,
                'external_event_id' => $externalEventId,
                'external_payment_id' => $externalPaymentId,
                'status' => $status,
                'processing_status' => 'received',
                'payload' => $this->redact($raw),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if ($externalEventId === null) {
                throw $e;
            }

            $existing = PaymentWebhookEvent::query()
                ->where('gateway_id', $gatewayId)
                ->where('external_event_id', $externalEventId)
                ->first();
            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function redact(array $raw): array
    {
        return SensitiveDataRedactor::redact($raw);
    }
}

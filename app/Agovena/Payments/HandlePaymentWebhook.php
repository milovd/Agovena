<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Secure webhook ingress: verify → persist idempotently → apply normalized status.
 */
final class HandlePaymentWebhook
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ApplyNormalizedPaymentStatus $applyStatus,
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

        $processed = DB::transaction(function () use ($event, $payload, $gatewayId): array {
            /** @var PaymentWebhookEvent $locked */
            $locked = PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->processing_status, ['processed', 'ignored'], true)) {
                return ['event' => $locked, 'duplicate' => true];
            }

            $blocked = false;
            if ($payload->externalPaymentId !== null) {
                $attempt = PaymentAttempt::query()
                    ->where('gateway_id', $gatewayId)
                    ->where('external_id', $payload->externalPaymentId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt !== null) {
                    $result = $this->applyStatus->handle($attempt, $payload->status);
                    $blocked = $result->blockedByTerminalState;
                }
            }

            if ($blocked) {
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
        $out = [];
        foreach ($raw as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'secret')
                || str_contains($lower, 'token')
                || str_contains($lower, 'password')
                || str_contains($lower, 'signature')
                || str_contains($lower, 'api_key')
                || $lower === 'key'
                || str_ends_with($lower, '_key')) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}

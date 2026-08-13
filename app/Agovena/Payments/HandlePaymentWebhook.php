<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Secure webhook ingress: verify → persist idempotently → apply normalized status.
 */
final class HandlePaymentWebhook
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
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

        if ($externalEventId !== null && $externalEventId !== '') {
            $existing = PaymentWebhookEvent::query()
                ->where('gateway_id', $gatewayId)
                ->where('external_event_id', $externalEventId)
                ->first();
            if ($existing !== null) {
                return new WebhookHandleResult($existing, duplicate: true);
            }
        }

        $event = PaymentWebhookEvent::query()->create([
            'gateway_id' => $gatewayId,
            'external_event_id' => $externalEventId,
            'external_payment_id' => $payload->externalPaymentId,
            'status' => $payload->status->value,
            'processing_status' => 'received',
            'payload' => $this->redact($payload->raw),
        ]);

        $processed = DB::transaction(function () use ($event, $payload, $gatewayId): PaymentWebhookEvent {
            /** @var PaymentWebhookEvent $locked */
            $locked = PaymentWebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->processing_status === 'processed') {
                return $locked;
            }

            if ($payload->externalPaymentId !== null) {
                $attempt = PaymentAttempt::query()
                    ->where('gateway_id', $gatewayId)
                    ->where('external_id', $payload->externalPaymentId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt !== null) {
                    $this->applyAttemptStatus($attempt, $payload->status);
                }
            }

            $locked->processing_status = 'processed';
            $locked->processed_at = now();
            $locked->save();

            return $locked->fresh() ?? $locked;
        });

        return new WebhookHandleResult($processed, duplicate: false);
    }

    private function applyAttemptStatus(PaymentAttempt $attempt, PaymentStatus $status): void
    {
        /** @var Payment $payment */
        $payment = Payment::query()->whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();

        if ($status === PaymentStatus::Paid && $payment->status !== PaymentStatus::Paid) {
            $order = $payment->order()->lockForUpdate()->first();
            if ($order !== null) {
                try {
                    $this->assertInvoiceCanBePaid->handle($order->loadMissing('invoice'));
                } catch (ValidationException) {
                    return;
                }
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->save();

            if ($order !== null && $order->status !== OrderStatus::Paid) {
                $order->status = OrderStatus::Paid;
                $order->save();
                event(new PaymentRecorded($payment->fresh() ?? $payment));
                event(new OrderPaid($order->fresh(['items', 'payment']) ?? $order));
            }

            $attempt->status = PaymentAttemptStatus::Succeeded;
            $attempt->completed_at = now();
            $attempt->save();

            return;
        }

        if (in_array($status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            $attempt->status = $status === PaymentStatus::Cancelled
                ? PaymentAttemptStatus::Cancelled
                : PaymentAttemptStatus::Failed;
            $attempt->completed_at = now();
            $attempt->save();
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
            if (str_contains($lower, 'secret') || str_contains($lower, 'token') || str_contains($lower, 'password') || str_contains($lower, 'signature')) {
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

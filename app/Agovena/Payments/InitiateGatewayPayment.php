<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a PaymentAttempt and asks the gateway to initiate provider payment.
 */
final class InitiateGatewayPayment
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function handle(
        Payment $payment,
        string $gatewayId,
        string $returnUrl,
        string $cancelUrl,
        ?string $idempotencyKey = null,
        ?string $checkoutMethod = null,
    ): PaymentAttempt {
        $gateway = $this->requireGateway($gatewayId);
        $payment->loadMissing('order');

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($payment, $gateway, $returnUrl, $cancelUrl, $idempotencyKey, $checkoutMethod): PaymentAttempt {
            $key = $idempotencyKey ?: 'att-'.Str::uuid()->toString();
            $attempt = PaymentAttempt::query()->create([
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'gateway_id' => $gateway->id(),
                'status' => PaymentAttemptStatus::Pending,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'idempotency_key' => $key,
                'request_meta' => $this->redact(array_filter([
                    'checkout_method' => $checkoutMethod,
                ])),
                'initiated_at' => now(),
            ]);

            try {
                $result = $gateway->initiate(new PaymentInitiation(
                    order: $payment->order,
                    payment: $payment,
                    returnUrl: $returnUrl,
                    cancelUrl: $cancelUrl,
                    metadata: array_filter([
                        'checkout_method' => $checkoutMethod,
                    ]),
                    idempotencyKey: $key,
                ));
            } catch (Throwable $exception) {
                report($exception);
                $attempt->status = PaymentAttemptStatus::Failed;
                $attempt->completed_at = now();
                $attempt->response_meta = ['error' => 'provider_unavailable'];
                $attempt->save();

                return $attempt;
            }

            $attempt->external_id = $result->externalId;
            $attempt->redirect_url = $result->redirectUrl;
            $attempt->response_meta = $this->redact($result->metadata);
            $attempt->status = match ($result->status) {
                'completed' => PaymentAttemptStatus::Succeeded,
                'failed' => PaymentAttemptStatus::Failed,
                'redirect', 'pending' => PaymentAttemptStatus::Processing,
                default => PaymentAttemptStatus::Processing,
            };
            if ($attempt->status === PaymentAttemptStatus::Succeeded || $attempt->status === PaymentAttemptStatus::Failed) {
                $attempt->completed_at = now();
            }
            $attempt->save();

            return $attempt->fresh() ?? $attempt;
        });
    }

    public function requireGateway(string $gatewayId): PaymentGateway
    {
        $gateway = $this->gateways->get($gatewayId);
        if ($gateway === null && $gatewayId === 'manual') {
            $gateway = app(ManualPaymentGateway::class);
        }
        if ($gateway === null && $gatewayId === 'development' && (bool) config('agovena.payments.allow_development_instant_pay')) {
            $gateway = app(DevelopmentPaymentGateway::class);
        }
        if ($gateway === null) {
            throw ValidationException::withMessages([
                'payment_method' => __('storefront.errors.payment_method_unavailable'),
            ]);
        }

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function redact(array $meta): array
    {
        $out = [];
        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'secret') || str_contains($lower, 'token') || str_contains($lower, 'password') || str_contains($lower, 'key')) {
                $out[$key] = '[redacted]';

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}

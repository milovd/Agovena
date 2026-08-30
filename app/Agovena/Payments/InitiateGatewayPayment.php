<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Security\SensitiveDataRedactor;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\UniqueConstraintViolationException;
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
        private readonly ApplyNormalizedPaymentStatus $applyStatus,
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

        try {
            return DB::transaction(function () use ($payment, $gateway, $returnUrl, $cancelUrl, $idempotencyKey, $checkoutMethod): PaymentAttempt {
                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $lockedPayment->loadMissing('order');
                $key = $idempotencyKey ?: 'att-'.Str::uuid()->toString();

                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                    if ($existing !== null) {
                        $this->assertAttemptMatches($existing, $lockedPayment, $gateway->id());

                        if ($existing->status === PaymentAttemptStatus::Succeeded) {
                            $this->applyStatus->handle($existing, PaymentStatus::Paid);
                        }

                        return $existing->fresh() ?? $existing;
                    }
                }

                $attempt = PaymentAttempt::query()->create([
                    'payment_id' => $lockedPayment->id,
                    'order_id' => $lockedPayment->order_id,
                    'gateway_id' => $gateway->id(),
                    'status' => PaymentAttemptStatus::Pending,
                    'amount' => $lockedPayment->amount,
                    'currency' => $lockedPayment->currency,
                    'idempotency_key' => $key,
                    'request_meta' => $this->redact(array_filter([
                        'checkout_method' => $checkoutMethod,
                    ])),
                    'initiated_at' => now(),
                ]);

                try {
                    $result = $gateway->initiate(new PaymentInitiation(
                        order: $lockedPayment->order,
                        payment: $lockedPayment,
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

                if ($result->status === 'completed') {
                    $this->applyStatus->handle($attempt, PaymentStatus::Paid);
                }

                return $attempt->fresh() ?? $attempt;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey === null || $idempotencyKey === '') {
                throw $exception;
            }

            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw $exception;
            }

            $this->assertAttemptMatches($existing, $payment, $gateway->id());

            return $existing;
        }
    }

    public function requireGateway(string $gatewayId): PaymentGateway
    {
        $gateway = $this->gateways->get($gatewayId);
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
        return SensitiveDataRedactor::redact($meta);
    }

    private function assertAttemptMatches(PaymentAttempt $attempt, Payment $payment, string $gatewayId): void
    {
        if ((int) $attempt->payment_id !== (int) $payment->id
            || (int) $attempt->order_id !== (int) $payment->order_id
            || $attempt->gateway_id !== $gatewayId
            || (int) $attempt->amount !== (int) $payment->amount
            || strtoupper($attempt->currency) !== strtoupper($payment->currency)
        ) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        }
    }
}

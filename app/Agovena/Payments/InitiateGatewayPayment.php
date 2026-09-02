<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Security\SensitiveDataRedactor;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        private readonly PaymentLifecycleLock $lifecycleLock,
    ) {}

    public function handle(
        Payment $payment,
        string $gatewayId,
        string $returnUrl,
        string $cancelUrl,
        ?string $idempotencyKey = null,
        ?string $checkoutMethod = null,
        bool $lifecycleLockHeld = false,
    ): PaymentAttempt {
        $gateway = $this->requireGateway($gatewayId);
        $payment->loadMissing('order');

        $operation = function () use ($payment, $gateway, $returnUrl, $cancelUrl, $idempotencyKey, $checkoutMethod): PaymentAttempt {
            [$attempt, $shouldInitiate] = DB::transaction(function () use ($payment, $gateway, $idempotencyKey, $checkoutMethod): array {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $lockedOrder->loadMissing('invoices');
                $key = $idempotencyKey ?: 'att-'.Str::uuid()->toString();

                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                    if ($existing !== null) {
                        $this->assertAttemptMatches($existing, $lockedPayment, $gateway->id());

                        if ($this->isStaleOpenAttempt($existing)) {
                            $this->closeStaleAttempt($existing, $lockedPayment);
                        }

                        return [$existing->fresh() ?? $existing, false];
                    }
                } else {
                    $existing = PaymentAttempt::query()
                        ->where('payment_id', $lockedPayment->id)
                        ->where('gateway_id', $gateway->id())
                        ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                    if ($existing !== null) {
                        if ($this->isStaleOpenAttempt($existing)) {
                            $this->closeStaleAttempt($existing, $lockedPayment);
                        }

                        return [$existing->fresh() ?? $existing, false];
                    }
                }

                if ($lockedPayment->reconciliation_status === 'manual_review') {
                    throw ValidationException::withMessages([
                        'payment' => __('storefront.errors.payment_unavailable'),
                    ]);
                }

                if ($lockedPayment->status !== PaymentStatus::Pending) {
                    throw ValidationException::withMessages([
                        'payment' => __('storefront.errors.payment_unavailable'),
                    ]);
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

                return [$attempt->fresh() ?? $attempt, true];
            });

            if (! $shouldInitiate) {
                return $this->settleIfSucceeded($payment, $attempt);
            }

            $this->assertProviderInitiationStillAllowed($payment, $attempt);

            try {
                $freshPayment = $payment->fresh() ?? $payment;
                $freshOrder = $payment->order->fresh(['invoices', 'items']) ?? $payment->order;
                $result = $gateway->initiate(new PaymentInitiation(
                    order: $freshOrder,
                    payment: $freshPayment,
                    returnUrl: $returnUrl,
                    cancelUrl: $cancelUrl,
                    metadata: array_filter([
                        'checkout_method' => $checkoutMethod,
                    ]),
                    idempotencyKey: $attempt->idempotency_key,
                ));
            } catch (Throwable $exception) {
                report($exception);
                $attempt = $this->recordUnknownProviderOutcome($payment, $attempt);

                return $attempt;
            }

            if ($result->status === 'unknown') {
                return $this->recordUnknownProviderOutcome(
                    $payment,
                    $attempt,
                    $result->externalId,
                    $result->metadata,
                );
            }

            $attempt = DB::transaction(function () use ($attempt, $result): PaymentAttempt {
                $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($lockedAttempt->status !== PaymentAttemptStatus::Pending) {
                    return $lockedAttempt->fresh() ?? $lockedAttempt;
                }

                $lockedAttempt->external_id = $result->externalId;
                $lockedAttempt->redirect_url = $result->redirectUrl;
                $lockedAttempt->response_meta = $this->redact($result->metadata);
                $lockedAttempt->status = match ($result->status) {
                    'completed' => PaymentAttemptStatus::Succeeded,
                    'failed' => PaymentAttemptStatus::Failed,
                    'redirect', 'pending' => PaymentAttemptStatus::Processing,
                    default => PaymentAttemptStatus::Processing,
                };
                if (in_array($lockedAttempt->status, [PaymentAttemptStatus::Succeeded, PaymentAttemptStatus::Failed], true)) {
                    $lockedAttempt->completed_at = now();
                }
                $lockedAttempt->save();

                return $lockedAttempt->fresh() ?? $lockedAttempt;
            });

            return $this->settleIfSucceeded($payment, $attempt);
        };

        try {
            $attempt = $lifecycleLockHeld
                ? $operation()
                : $this->lifecycleLock->run($payment->order_id, $operation);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey === null || $idempotencyKey === '') {
                throw $exception;
            }

            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw $exception;
            }

            $this->assertAttemptMatches($existing, $payment, $gateway->id());

            $attempt = $existing;
        }

        return $attempt->fresh() ?? $attempt;
    }

    private function assertProviderInitiationStillAllowed(Payment $payment, PaymentAttempt $attempt): void
    {
        $allowed = DB::transaction(function () use ($payment): bool {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->loadMissing('invoices');
            $lockedOrder->setRelation('payment', $lockedPayment);

            return $lockedPayment->reconciliation_status !== 'manual_review'
                && $lockedPayment->status === PaymentStatus::Pending
                && $lockedOrder->status === OrderStatus::Pending
                && ! $lockedOrder->invoices->contains(static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Void);
        });

        if ($allowed) {
            return;
        }

        DB::transaction(function () use ($attempt): void {
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedAttempt->status, [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing], true)) {
                $lockedAttempt->status = PaymentAttemptStatus::Failed;
                $lockedAttempt->completed_at = now();
                $lockedAttempt->response_meta = array_merge(
                    is_array($lockedAttempt->response_meta) ? $lockedAttempt->response_meta : [],
                    ['reason' => 'payment_not_available_before_provider_initiation'],
                );
                $lockedAttempt->save();
            }
        });

        throw ValidationException::withMessages([
            'payment' => __('storefront.errors.payment_unavailable'),
        ]);
    }

    private function settleIfSucceeded(Payment $payment, PaymentAttempt $attempt): PaymentAttempt
    {
        if ($attempt->status === PaymentAttemptStatus::Succeeded) {
            try {
                $settlement = $this->applyStatus->handle($attempt, PaymentStatus::Paid);
                if (! $settlement->applied || $settlement->blockedByTerminalState) {
                    $this->recordSettlementRecovery($payment, $attempt);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->recordSettlementRecovery(
                    $payment,
                    $attempt,
                    'completed_provider_payment_local_settlement_failed',
                );
            }
        }

        return $attempt->fresh() ?? $attempt;
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

    private function recordSettlementRecovery(
        Payment $payment,
        PaymentAttempt $attempt,
        string $reason = 'completed_provider_payment_local_settlement_blocked',
    ): void {
        DB::transaction(function () use ($payment, $attempt, $reason): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedPayment->order_id !== (int) $lockedOrder->id
                || (int) $lockedAttempt->payment_id !== (int) $lockedPayment->id
            ) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            $lockedPayment->reconciliation_status = 'manual_review';
            $lockedPayment->reconciliation_meta = [
                'reason' => $reason,
                'gateway_id' => $lockedAttempt->gateway_id,
                'attempt_id' => $lockedAttempt->id,
                'recorded_at' => now()->toIso8601String(),
            ];
            $lockedPayment->save();

            $lockedAttempt->response_meta = array_merge($lockedAttempt->response_meta ?? [], [
                'settlement' => 'manual_review',
                'settlement_reason' => $reason === 'completed_provider_payment_local_settlement_failed'
                    ? 'local_settlement_failed'
                    : 'local_settlement_blocked',
            ]);
            $lockedAttempt->save();
        });
    }

    private function recordUnknownProviderOutcome(
        Payment $payment,
        PaymentAttempt $attempt,
        ?string $externalId = null,
        array $metadata = [],
    ): PaymentAttempt {
        return DB::transaction(function () use ($payment, $attempt, $externalId, $metadata): PaymentAttempt {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedPayment->order_id !== (int) $lockedOrder->id
                || (int) $lockedAttempt->payment_id !== (int) $lockedPayment->id
                || (int) $lockedAttempt->order_id !== (int) $lockedOrder->id
            ) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            $lockedAttempt->status = PaymentAttemptStatus::Failed;
            $lockedAttempt->external_id = $externalId;
            $lockedAttempt->completed_at = $lockedAttempt->completed_at ?? now();
            $lockedAttempt->response_meta = array_merge($lockedAttempt->response_meta ?? [], $this->redact($metadata), [
                'error' => 'provider_unavailable',
                'provider_outcome' => 'unknown',
            ]);
            $lockedAttempt->save();

            $lockedPayment->reconciliation_status = 'manual_review';
            $lockedPayment->reconciliation_meta = [
                'reason' => 'provider_initiation_outcome_unknown',
                'gateway_id' => $lockedAttempt->gateway_id,
                'attempt_id' => $lockedAttempt->id,
                'recorded_at' => now()->toIso8601String(),
            ];
            $lockedPayment->save();

            return $lockedAttempt->fresh() ?? $lockedAttempt;
        });
    }

    private function isStaleOpenAttempt(PaymentAttempt $attempt): bool
    {
        if (! in_array($attempt->status, [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing], true)) {
            return false;
        }

        $startedAt = $attempt->initiated_at ?? $attempt->created_at;

        return $startedAt !== null
            && $startedAt->lte(now()->subSeconds(max(60, (int) config('agovena.payments.pending_attempt_stale_seconds', 900))));
    }

    private function closeStaleAttempt(PaymentAttempt $attempt, Payment $payment): void
    {
        $attempt->status = PaymentAttemptStatus::Failed;
        $attempt->completed_at = $attempt->completed_at ?? now();
        $attempt->response_meta = array_merge($attempt->response_meta ?? [], [
            'error' => 'stale_provider_initiation',
            'provider_outcome' => 'unknown',
        ]);
        $attempt->save();

        $payment->reconciliation_status = 'manual_review';
        $payment->reconciliation_meta = [
            'reason' => 'provider_initiation_crash_recovery_required',
            'gateway_id' => $attempt->gateway_id,
            'attempt_id' => $attempt->id,
            'recorded_at' => now()->toIso8601String(),
        ];
        $payment->save();
    }
}

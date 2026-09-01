<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer/checkout payment initiation. Return URLs are never treated as payment proof.
 */
final class StartOrderPayment
{
    public function __construct(
        private readonly InitiateGatewayPayment $initiate,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
        private readonly PaymentLifecycleLock $lifecycleLock,
    ) {}

    public function handle(
        Order $order,
        string $gatewayId,
        string $returnUrl,
        string $cancelUrl,
        ?string $idempotencyKey = null,
        ?string $checkoutMethod = null,
    ): PaymentAttempt {
        $selection = str_contains($gatewayId, ':')
            ? CheckoutPaymentSelection::parse($gatewayId)
            : new CheckoutPaymentSelection($gatewayId, $gatewayId, $checkoutMethod);

        try {
            return $this->lifecycleLock->run($order->id, function () use ($order, $selection, $returnUrl, $cancelUrl, $idempotencyKey, $checkoutMethod): PaymentAttempt {
                $order->loadMissing('payment', 'invoice');
                $this->assertInvoiceCanBePaid->handle($order);
                $payment = $order->payment;
                if ($payment === null) {
                    throw ValidationException::withMessages([
                        'payment' => __('storefront.errors.payment_unavailable'),
                    ]);
                }

                $this->requireGateway($selection->gatewayId);

                $payment = DB::transaction(function () use ($order, $selection, $idempotencyKey): Payment {
                    /** @var Order $lockedOrder */
                    $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $lockedOrder->loadMissing('payment', 'invoice');
                    $lockedPayment = $lockedOrder->payment;

                    if ($lockedPayment === null) {
                        throw ValidationException::withMessages([
                            'payment' => __('storefront.errors.payment_unavailable'),
                        ]);
                    }

                    $existingAttempt = $idempotencyKey !== null && $idempotencyKey !== ''
                        ? PaymentAttempt::query()
                            ->where('payment_id', $lockedPayment->id)
                            ->where('gateway_id', $selection->gatewayId)
                            ->where('idempotency_key', $idempotencyKey)
                            ->exists()
                        : false;
                    if ($lockedPayment->reconciliation_status === 'manual_review' && ! $existingAttempt) {
                        throw ValidationException::withMessages([
                            'payment' => __('storefront.errors.payment_unavailable'),
                        ]);
                    }

                    if ($lockedPayment->status === PaymentStatus::Paid) {
                        throw ValidationException::withMessages([
                            'payment' => __('storefront.errors.already_paid'),
                        ]);
                    }

                    if (! $lockedOrder->isRetryablePayment()) {
                        throw ValidationException::withMessages([
                            'payment' => __('storefront.errors.payment_unavailable'),
                        ]);
                    }

                    $this->assertInvoiceCanBePaid->handle($lockedOrder);

                    if (in_array($lockedPayment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true)) {
                        $lockedPayment->status = PaymentStatus::Pending;
                        $lockedPayment->save();
                    }

                    if ($lockedPayment->method !== $selection->gatewayId) {
                        $lockedPayment->method = $selection->gatewayId;
                        $lockedPayment->save();
                    }

                    return $lockedPayment->fresh() ?? $lockedPayment;
                });

                $open = PaymentAttempt::query()
                    ->where('payment_id', $payment->id)
                    ->where('gateway_id', $selection->gatewayId)
                    ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                    ->latest('id')
                    ->first();

                if ($open !== null) {
                    if ($this->isStaleOpenAttempt($open)) {
                        return $this->initiate->handle(
                            $payment,
                            $selection->gatewayId,
                            $returnUrl,
                            $cancelUrl,
                            $idempotencyKey,
                            $selection->method ?? $checkoutMethod,
                            lifecycleLockHeld: true,
                        );
                    }

                    return $open;
                }

                return $this->initiate->handle(
                    $payment,
                    $selection->gatewayId,
                    $returnUrl,
                    $cancelUrl,
                    $idempotencyKey,
                    $selection->method ?? $checkoutMethod,
                    lifecycleLockHeld: true,
                );
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        }
    }

    public function requireGateway(string $gatewayId): PaymentGateway
    {
        $gateway = $this->gateways->get($gatewayId);
        if ($gateway === null) {
            $gateway = $this->coreFallback($gatewayId);
        }

        if ($gateway === null) {
            throw ValidationException::withMessages([
                'payment_method' => __('storefront.errors.payment_method_unavailable'),
            ]);
        }

        return $gateway;
    }

    private function isStaleOpenAttempt(PaymentAttempt $attempt): bool
    {
        $startedAt = $attempt->initiated_at ?? $attempt->created_at;

        return $startedAt !== null
            && $startedAt->lte(now()->subSeconds(max(60, (int) config('agovena.payments.pending_attempt_stale_seconds', 900))));
    }

    private function coreFallback(string $gatewayId): ?PaymentGateway
    {
        return match ($gatewayId) {
            'development' => (bool) config('agovena.payments.allow_development_instant_pay')
                ? app(DevelopmentPaymentGateway::class)
                : null,
            default => null,
        };
    }
}

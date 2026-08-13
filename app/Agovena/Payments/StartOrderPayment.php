<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Validation\ValidationException;

/**
 * Customer/checkout payment initiation. Return URLs are never treated as payment proof.
 */
final class StartOrderPayment
{
    public function __construct(
        private readonly InitiateGatewayPayment $initiate,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function handle(
        Order $order,
        string $gatewayId,
        string $returnUrl,
        string $cancelUrl,
        ?string $idempotencyKey = null,
    ): PaymentAttempt {
        $order->loadMissing('payment');
        $payment = $order->payment;

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        }

        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.already_paid'),
            ]);
        }

        if (! $order->isAwaitingPayment()) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        }

        $this->requireGateway($gatewayId);

        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            $payment->status = PaymentStatus::Pending;
            $payment->save();
        }

        $method = PaymentMethod::tryFrom($gatewayId);
        if ($method !== null && $payment->method !== $method) {
            $payment->method = $method;
            $payment->save();
        }

        $open = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->where('gateway_id', $gatewayId)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->latest('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return $this->initiate->handle(
            $payment->fresh() ?? $payment,
            $gatewayId,
            $returnUrl,
            $cancelUrl,
            $idempotencyKey,
        );
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

    private function coreFallback(string $gatewayId): ?PaymentGateway
    {
        return match ($gatewayId) {
            'manual' => app(ManualPaymentGateway::class),
            'development' => (bool) config('agovena.payments.allow_development_instant_pay')
                ? app(DevelopmentPaymentGateway::class)
                : null,
            default => null,
        };
    }
}

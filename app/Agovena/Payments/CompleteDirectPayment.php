<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteDirectPayment
{
    public function __construct(
        private readonly ApplyNormalizedPaymentStatus $applyStatus,
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
        private readonly PaymentLifecycleLock $lifecycleLock,
    ) {}

    public function handle(
        Order $order,
        string $gatewayId,
        ?string $reference = null,
        ?string $requiredPaymentMethod = null,
        ?int $requiredAmount = null,
        bool $lifecycleLockHeld = false,
    ): Payment {
        $operation = fn (): Payment => $this->complete($order, $gatewayId, $reference, $requiredPaymentMethod, $requiredAmount);

        return $lifecycleLockHeld ? $operation() : $this->lifecycleLock->run($order->id, $operation);
    }

    private function complete(
        Order $order,
        string $gatewayId,
        ?string $reference,
        ?string $requiredPaymentMethod,
        ?int $requiredAmount,
    ): Payment {
        $payment = DB::transaction(function () use ($order, $gatewayId, $requiredPaymentMethod, $requiredAmount): Payment {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var Payment $payment */
            $payment = Payment::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->firstOrFail();

            if (in_array($payment->status, [
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
                PaymentStatus::Cancelled,
                PaymentStatus::Expired,
            ], true)) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            $lockedOrder->loadMissing('invoice');
            $this->assertInvoiceCanBePaid->handle($lockedOrder);

            if ($requiredPaymentMethod !== null && $payment->method !== $requiredPaymentMethod) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            if ($requiredAmount !== null && (int) $payment->amount !== $requiredAmount) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            if ($gatewayId === 'account_balance' && $payment->method !== 'account_balance') {
                $payment->method = 'account_balance';
                $payment->save();
            }

            $attempt = PaymentAttempt::query()
                ->where('payment_id', $payment->id)
                ->where('gateway_id', $gatewayId)
                ->where('idempotency_key', $this->idempotencyKey($payment, $gatewayId))
                ->lockForUpdate()
                ->first();

            if ($attempt === null) {
                $attempt = PaymentAttempt::query()->create([
                    'payment_id' => $payment->id,
                    'order_id' => $lockedOrder->id,
                    'gateway_id' => $gatewayId,
                    'status' => PaymentAttemptStatus::Processing,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'idempotency_key' => $this->idempotencyKey($payment, $gatewayId),
                    'request_meta' => ['purpose' => 'direct_completion'],
                    'initiated_at' => now(),
                ]);
            }

            return $payment;
        });

        if ($payment->status === PaymentStatus::Paid) {
            return $payment->fresh() ?? $payment;
        }

        $attempt = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->where('gateway_id', $gatewayId)
            ->where('idempotency_key', $this->idempotencyKey($payment, $gatewayId))
            ->firstOrFail();

        $result = $this->applyStatus->handle($attempt, PaymentStatus::Paid);
        if (! $result->applied || $result->blockedByTerminalState) {
            throw ValidationException::withMessages([
                'payment' => __('storefront.errors.payment_unavailable'),
            ]);
        }

        $payment = Payment::query()->findOrFail($payment->id);
        if (filled($reference) && $payment->reference !== $reference) {
            $payment->reference = $reference;
            $payment->save();
        }

        return $payment->fresh() ?? $payment;
    }

    private function idempotencyKey(Payment $payment, string $gatewayId): string
    {
        return 'direct:'.$gatewayId.':payment:'.$payment->id;
    }
}

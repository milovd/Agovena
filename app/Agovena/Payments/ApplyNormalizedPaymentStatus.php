<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a provider-normalized PaymentStatus to PaymentAttempt + Payment + Order.
 * Return URLs must never call this with customer-supplied status values.
 */
final class ApplyNormalizedPaymentStatus
{
    public function __construct(
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
    ) {}

    public function handle(PaymentAttempt $attempt, PaymentStatus $status): PaymentStatusApplyResult
    {
        return $this->handleLocked($attempt, $status);
    }

    private function handleLocked(PaymentAttempt $attempt, PaymentStatus $status): PaymentStatusApplyResult
    {
        return DB::transaction(function () use ($attempt, $status): PaymentStatusApplyResult {
            /** @var Order $order */
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();
            if ((int) $payment->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }
            /** @var PaymentAttempt $lockedAttempt */
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedAttempt->payment_id !== (int) $payment->id
                || (int) $lockedAttempt->order_id !== (int) $order->id
            ) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            if ($this->isPaidLike($payment) && $this->isFailure($status)) {
                return new PaymentStatusApplyResult($lockedAttempt, applied: false);
            }

            if ($status === PaymentStatus::Paid) {
                if (in_array($payment->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
                    return new PaymentStatusApplyResult($lockedAttempt, applied: false);
                }

                try {
                    $this->assertInvoiceCanBePaid->handle($order->loadMissing('invoices'));
                } catch (ValidationException) {
                    return new PaymentStatusApplyResult(
                        $lockedAttempt,
                        applied: false,
                        blockedByTerminalState: true,
                    );
                }

                $paymentWasPaid = $payment->status === PaymentStatus::Paid;
                if ($payment->status !== PaymentStatus::Paid) {
                    $payment->status = PaymentStatus::Paid;
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->save();
                }

                $lockedAttempt->status = PaymentAttemptStatus::Succeeded;
                $lockedAttempt->completed_at = $lockedAttempt->completed_at ?? now();
                $lockedAttempt->save();

                $orderWasPaid = $order->status === OrderStatus::Paid;
                if (! $orderWasPaid) {
                    $order->status = OrderStatus::Paid;
                    $order->save();
                }

                if (! $paymentWasPaid) {
                    event(new PaymentRecorded($payment->fresh() ?? $payment));
                }
                if (! $orderWasPaid) {
                    event(new OrderPaid($order->fresh(['items', 'payment']) ?? $order));
                }

                return new PaymentStatusApplyResult($lockedAttempt->fresh() ?? $lockedAttempt, applied: true);
            }

            if ($status === PaymentStatus::Refunded || $status === PaymentStatus::PartiallyRefunded) {
                if ($payment->status === PaymentStatus::Refunded && $status === PaymentStatus::PartiallyRefunded) {
                    return new PaymentStatusApplyResult($lockedAttempt, applied: false);
                }

                if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
                    $payment->status = $status;
                    $payment->save();

                    return new PaymentStatusApplyResult($lockedAttempt, applied: true);
                }

                return new PaymentStatusApplyResult($lockedAttempt, applied: false);
            }

            if ($this->isFailure($status)) {
                if (! $this->isPaidLike($payment)) {
                    $payment->status = $status;
                    $payment->save();
                }

                $lockedAttempt->status = match ($status) {
                    PaymentStatus::Cancelled => PaymentAttemptStatus::Cancelled,
                    PaymentStatus::Expired => PaymentAttemptStatus::Expired,
                    default => PaymentAttemptStatus::Failed,
                };
                $lockedAttempt->completed_at = now();
                $lockedAttempt->save();

                return new PaymentStatusApplyResult($lockedAttempt->fresh() ?? $lockedAttempt, applied: true);
            }

            return new PaymentStatusApplyResult($lockedAttempt, applied: false);
        });
    }

    private function isPaidLike(Payment $payment): bool
    {
        return in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
        ], true);
    }

    private function isFailure(PaymentStatus $status): bool
    {
        return in_array($status, [
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
            PaymentStatus::Expired,
        ], true);
    }
}

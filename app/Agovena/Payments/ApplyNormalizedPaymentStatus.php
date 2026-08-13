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

    public function handle(PaymentAttempt $attempt, PaymentStatus $status): PaymentAttempt
    {
        /** @var Payment $payment */
        $payment = Payment::query()->whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();

        if ($this->isPaidLike($payment) && $this->isFailure($status)) {
            return $attempt;
        }

        if ($status === PaymentStatus::Paid && $payment->status !== PaymentStatus::Paid) {
            $order = $payment->order()->lockForUpdate()->first();
            if ($order !== null) {
                try {
                    $this->assertInvoiceCanBePaid->handle($order->loadMissing('invoice'));
                } catch (ValidationException) {
                    return $attempt;
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

            return $attempt->fresh() ?? $attempt;
        }

        if ($status === PaymentStatus::Refunded || $status === PaymentStatus::PartiallyRefunded) {
            if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
                $payment->status = $status;
                $payment->save();
            }

            return $attempt;
        }

        if ($this->isFailure($status)) {
            $attempt->status = match ($status) {
                PaymentStatus::Cancelled => PaymentAttemptStatus::Cancelled,
                PaymentStatus::Expired => PaymentAttemptStatus::Expired,
                default => PaymentAttemptStatus::Failed,
            };
            $attempt->completed_at = now();
            $attempt->save();
        }

        return $attempt->fresh() ?? $attempt;
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

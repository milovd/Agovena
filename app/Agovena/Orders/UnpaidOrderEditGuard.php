<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Validation\ValidationException;

/**
 * Decides whether staff may still change what an order is for (its customer or its lines).
 * Only an open order without money movement qualifies: once a payment settled, a payment
 * attempt runs, or an invoice carries financial history, changes go through refunds,
 * credit notes or the subscription instead.
 */
final class UnpaidOrderEditGuard
{
    public function blockingReason(Order $order, bool $forCustomerChange = false): ?string
    {
        $order->loadMissing('payment');

        if ($order->status === OrderStatus::Paid || $this->hasSettledPayment($order)) {
            return __('admin.orders.edit_lock.paid');
        }

        if ($order->status !== OrderStatus::Pending) {
            return __('admin.orders.edit_lock.closed');
        }

        if ($this->hasOpenPaymentAttempt($order)) {
            return __('admin.orders.edit_lock.payment_in_progress');
        }

        if ($this->hasFinancialInvoice($order)) {
            return __('admin.orders.edit_lock.invoice_history');
        }

        // Account credit was taken from the current customer's ledger.
        if ($forCustomerChange && (int) $order->credit_amount > 0) {
            return __('admin.orders.edit_lock.credit_applied');
        }

        return null;
    }

    public function assertEditable(Order $order, bool $forCustomerChange = false): void
    {
        $reason = $this->blockingReason($order, $forCustomerChange);
        if ($reason !== null) {
            throw ValidationException::withMessages(['order' => $reason]);
        }
    }

    private function hasSettledPayment(Order $order): bool
    {
        $payment = $order->payment;

        return $payment !== null
            && ($payment->paid_at !== null || in_array($payment->status, [
                PaymentStatus::Paid,
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
            ], true));
    }

    private function hasOpenPaymentAttempt(Order $order): bool
    {
        $paymentId = $order->payment?->id;

        return $paymentId !== null && PaymentAttempt::query()
            ->where('payment_id', $paymentId)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->exists();
    }

    private function hasFinancialInvoice(Order $order): bool
    {
        return $order->invoices()
            ->where('status', '!=', InvoiceStatus::Void)
            ->where(function ($query): void {
                $query->where('status', '!=', InvoiceStatus::Issued)
                    ->orWhereNotNull('paid_at')
                    ->orWhereHas('creditNotes')
                    ->orWhereHas('refunds');
            })
            ->exists();
    }
}

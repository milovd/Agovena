<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LinkInvoiceToOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Invoice $invoice, Order $order, User $staff): Invoice
    {
        abort_unless($staff->can('invoices.manage'), 403);

        return DB::transaction(function () use ($invoice, $order, $staff): Invoice {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedInvoice->order_id === (int) $lockedOrder->id) {
                return $lockedInvoice->fresh(['order']) ?? $lockedInvoice;
            }

            $this->assertLinkable($lockedInvoice, $lockedOrder);

            $before = [
                'invoice_id' => $lockedInvoice->id,
                'invoice_number' => $lockedInvoice->number,
                'order_id' => $lockedInvoice->order_id,
            ];
            $lockedInvoice->order_id = $lockedOrder->id;
            $lockedInvoice->save();

            $fresh = $lockedInvoice->fresh(['order']) ?? $lockedInvoice;
            $this->audit->log('invoice.linked_to_order', $fresh, [
                ...$before,
                'new_order_id' => $lockedOrder->id,
                'staff_id' => $staff->id,
            ]);

            return $fresh;
        });
    }

    private function assertLinkable(Invoice $invoice, Order $order): void
    {
        if ($invoice->order_id !== null || ! $invoice->canChangeOrderAssociation()) {
            $this->throwCannotLink();
        }

        if ($order->status !== OrderStatus::Pending
            || $this->orderHasSettledPayment($order)
            || $this->orderHasFinancialInvoice($order)
        ) {
            $this->throwCannotLink();
        }

        if ($this->hasOpenPaymentAttempt($order)) {
            $this->throwCannotLink();
        }

        if (strcasecmp($invoice->currency, $order->currency) !== 0
            || ($invoice->customer_id !== null
                && $order->customer_id !== null
                && (int) $invoice->customer_id !== (int) $order->customer_id)
            || strcasecmp(trim($invoice->customer_email), trim($order->customer_email)) !== 0
        ) {
            $this->throwCannotLink();
        }

    }

    private function orderHasSettledPayment(Order $order): bool
    {
        $payment = $order->payment;

        return $payment !== null
            && ($payment->paid_at !== null || in_array($payment->status, [
                PaymentStatus::Paid,
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
            ], true));
    }

    private function orderHasFinancialInvoice(Order $order): bool
    {
        return $order->invoices()
            ->where(function ($query): void {
                $query->where('status', '!=', InvoiceStatus::Issued)
                    ->orWhereNotNull('paid_at')
                    ->orWhereHas('creditNotes')
                    ->orWhereHas('refunds');
            })
            ->exists();
    }

    private function hasOpenPaymentAttempt(Order $order): bool
    {
        $paymentId = $order->payment?->id;
        if ($paymentId === null) {
            return false;
        }

        return PaymentAttempt::query()
            ->where('payment_id', $paymentId)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->exists();
    }

    private function throwCannotLink(): never
    {
        throw ValidationException::withMessages([
            'invoice' => __('admin.invoices.cannot_link'),
        ]);
    }
}

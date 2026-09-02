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

final class UnlinkInvoiceFromOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Invoice $invoice, User $staff): Invoice
    {
        abort_unless($staff->can('invoices.manage'), 403);

        return DB::transaction(function () use ($invoice, $staff): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->order_id === null) {
                return $locked->fresh(['order']) ?? $locked;
            }

            $order = Order::query()->whereKey($locked->order_id)->first();
            $this->assertUnlinkable($locked, $order);

            $before = [
                'invoice_id' => $locked->id,
                'invoice_number' => $locked->number,
                'order_id' => $locked->order_id,
            ];
            $locked->order_id = null;
            $locked->save();

            $fresh = $locked->fresh(['order']) ?? $locked;
            $this->audit->log('invoice.unlinked_from_order', $fresh, [
                ...$before,
                'staff_id' => $staff->id,
            ]);

            return $fresh;
        });
    }

    private function assertUnlinkable(Invoice $invoice, ?Order $order): void
    {
        if ($order === null || $order->status !== OrderStatus::Pending || ! $invoice->canChangeOrderAssociation()) {
            $this->throwCannotUnlink();
        }

        if ($order->invoices()
            ->where('id', '<>', $invoice->id)
            ->where(function ($query): void {
                $query->where('status', '!=', InvoiceStatus::Issued)
                    ->orWhereNotNull('paid_at')
                    ->orWhereHas('creditNotes')
                    ->orWhereHas('refunds');
            })
            ->exists()) {
            $this->throwCannotUnlink();
        }

        $payment = $order->payment;
        if ($payment !== null && ($payment->paid_at !== null || in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
        ], true))) {
            $this->throwCannotUnlink();
        }

        if ($this->hasOpenPaymentAttempt($payment?->id)) {
            $this->throwCannotUnlink();
        }
    }

    private function hasOpenPaymentAttempt(?int $paymentId): bool
    {
        if ($paymentId === null) {
            return false;
        }

        return PaymentAttempt::query()
            ->where('payment_id', $paymentId)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->exists();
    }

    private function throwCannotUnlink(): never
    {
        throw ValidationException::withMessages([
            'invoice' => __('admin.invoices.cannot_unlink'),
        ]);
    }
}

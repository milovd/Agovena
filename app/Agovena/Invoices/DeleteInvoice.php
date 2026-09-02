<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Invoice $invoice, User $staff): void
    {
        abort_unless($staff->can('invoices.delete'), 403);

        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->order_id === null
                ? null
                : Payment::query()->where('order_id', $locked->order_id)->lockForUpdate()->first();

            $this->assertDeletable($locked, $payment);

            $before = [
                'id' => $locked->id,
                'number' => $locked->number,
                'status' => $locked->status->value,
                'order_id' => $locked->order_id,
                'payment_id' => $payment?->id,
                'item_ids' => InvoiceItem::query()->where('invoice_id', $locked->id)->pluck('id')->all(),
            ];

            InvoiceItem::query()->where('invoice_id', $locked->id)->delete();
            Invoice::withoutEvents(static fn () => $locked->delete());

            $this->audit->log('invoice.deleted', $locked, $before);
        });
    }

    private function assertDeletable(Invoice $invoice, ?Payment $payment): void
    {
        if ($invoice->status !== InvoiceStatus::Issued || $invoice->paid_at !== null) {
            $this->throwCannotDelete();
        }

        if ($invoice->creditNotes()->exists() || $invoice->refunds()->exists()) {
            $this->throwCannotDelete();
        }

        if ($payment === null) {
            return;
        }

        if ($payment->paid_at !== null || in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
        ], true)) {
            $this->throwCannotDelete();
        }

        if (Refund::query()->where('order_id', $payment->order_id)->exists()) {
            $this->throwCannotDelete();
        }

        if (PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->whereNotNull('external_id')
            ->where('external_id', '<>', '')
            ->exists()) {
            $this->throwCannotDelete();
        }
    }

    private function throwCannotDelete(): never
    {
        throw ValidationException::withMessages([
            'invoice' => __('admin.invoices.cannot_delete'),
        ]);
    }
}

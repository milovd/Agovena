<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class DeleteOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Order $order, User $staff): void
    {
        abort_unless($staff->can('orders.delete'), 403);

        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $invoices = Invoice::query()->where('order_id', $locked->id)->lockForUpdate()->get();
            $payments = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->get();

            $this->assertDeletable($locked, $invoices, $payments);

            $before = [
                'id' => $locked->id,
                'number' => $locked->number,
                'status' => $locked->status->value,
                'invoice_ids' => $invoices->modelKeys(),
                'payment_ids' => $payments->modelKeys(),
            ];

            if (Schema::hasTable('postnl_shipments')) {
                DB::table('postnl_shipments')->where('order_id', $locked->id)->delete();
            }

            foreach ($invoices as $invoice) {
                InvoiceItem::query()->where('invoice_id', $invoice->id)->delete();
                Invoice::withoutEvents(static fn () => $invoice->delete());
            }

            $locked->delete();

            $this->audit->log('order.deleted', $locked, $before);
        });
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     */
    private function assertDeletable(Order $order, Collection $invoices, Collection $payments): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_delete'),
            ]);
        }

        if ($invoices->contains(static fn (Invoice $invoice): bool => $invoice->status !== InvoiceStatus::Issued || $invoice->paid_at !== null)) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_delete'),
            ]);
        }

        if ($payments->contains(static fn (Payment $payment): bool => $payment->paid_at !== null || in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
        ], true))) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_delete'),
            ]);
        }

        $paymentIds = $payments->modelKeys();
        if ($paymentIds !== [] && PaymentAttempt::query()
            ->whereIn('payment_id', $paymentIds)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->whereNotNull('external_id')
            ->where('external_id', '<>', '')
            ->exists()) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_delete'),
            ]);
        }

        $invoiceIds = $invoices->modelKeys();
        $hasCreditNotes = CreditNote::query()
            ->where(function ($query) use ($order, $invoiceIds): void {
                $query->where('order_id', $order->id);
                if ($invoiceIds !== []) {
                    $query->orWhereIn('invoice_id', $invoiceIds);
                }
            })
            ->exists();

        $hasRefunds = Refund::query()
            ->where(function ($query) use ($order, $invoiceIds, $paymentIds): void {
                $query->where('order_id', $order->id);
                if ($invoiceIds !== []) {
                    $query->orWhereIn('invoice_id', $invoiceIds);
                }
                if ($paymentIds !== []) {
                    $query->orWhereIn('payment_id', $paymentIds);
                }
            })
            ->exists();

        if ($hasCreditNotes || $hasRefunds) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_delete'),
            ]);
        }
    }
}

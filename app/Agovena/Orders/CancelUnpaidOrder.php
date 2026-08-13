<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\InvoiceVoided;
use App\Events\OrderCancelled;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CancelUnpaidOrder
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Order $order, UnpaidOrderCancelSource $source, ?User $staff = null): Order
    {
        if ($source === UnpaidOrderCancelSource::Staff) {
            if ($staff === null || (! $staff->can('orders.cancel') && ! $staff->can('invoices.void'))) {
                abort(403);
            }
        }

        return DB::transaction(function () use ($order, $source, $staff): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->load(['invoice', 'payment']);

            if (! $locked->isAwaitingPayment()) {
                throw ValidationException::withMessages([
                    'order' => $source === UnpaidOrderCancelSource::Customer
                        ? __('customer.account.cannot_cancel_order')
                        : __('admin.orders.cannot_cancel'),
                ]);
            }

            $invoice = $locked->invoice;
            if ($invoice !== null) {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                if (! $invoice->canVoid()) {
                    throw ValidationException::withMessages([
                        'order' => __('admin.invoices.cannot_void'),
                    ]);
                }

                $invoice->status = InvoiceStatus::Void;
                $invoice->save();
            }

            if ($locked->status === OrderStatus::Pending) {
                $locked->status = OrderStatus::Cancelled;
                $locked->save();
            }

            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            if ($payment !== null && in_array($payment->status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true)) {
                $payment->status = PaymentStatus::Cancelled;
                $payment->save();

                PaymentAttempt::query()
                    ->where('payment_id', $payment->id)
                    ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                    ->update([
                        'status' => PaymentAttemptStatus::Cancelled,
                        'completed_at' => now(),
                    ]);
            }

            $fresh = $locked->fresh(['items', 'invoice', 'payment'])
                ?? throw new RuntimeException('Cancelled order disappeared.');

            if ($fresh->invoice !== null) {
                $this->audit->log('invoice.voided', $fresh->invoice, [
                    'invoice_number' => $fresh->invoice->number,
                    'order_id' => $fresh->id,
                    'source' => $source->value,
                    'staff_id' => $staff?->id,
                ]);
                event(new InvoiceVoided($fresh->invoice));
            }

            $this->audit->log('order.cancelled', $fresh, [
                'order_number' => $fresh->number,
                'source' => $source->value,
                'staff_id' => $staff?->id,
            ]);
            event(new OrderCancelled($fresh));

            return $fresh;
        });
    }
}

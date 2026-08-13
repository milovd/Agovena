<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\InvoiceVoided;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class VoidInvoice
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Invoice $invoice, User $staff): Invoice
    {
        if (! $staff->can('invoices.void')) {
            abort(403);
        }

        return DB::transaction(function () use ($invoice, $staff): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canVoid()) {
                throw ValidationException::withMessages([
                    'invoice' => __('admin.invoices.cannot_void'),
                ]);
            }

            $locked->status = InvoiceStatus::Void;
            $locked->save();

            if ($locked->order_id !== null) {
                /** @var Order $order */
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                if ($order->status === OrderStatus::Pending) {
                    $order->status = OrderStatus::Cancelled;
                    $order->save();
                }

                $payment = Payment::query()->where('order_id', $order->id)->lockForUpdate()->first();
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
            }

            $fresh = $locked->fresh(['items']) ?? throw new RuntimeException('Voided invoice disappeared.');

            $this->audit->log('invoice.voided', $fresh, [
                'invoice_number' => $fresh->number,
                'order_id' => $fresh->order_id,
                'staff_id' => $staff->id,
            ]);

            event(new InvoiceVoided($fresh));

            return $fresh;
        });
    }
}

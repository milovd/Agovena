<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceVoided;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class VoidInvoice
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CancelUnpaidOrder $cancelUnpaidOrder,
    ) {}

    public function handle(Invoice $invoice, User $staff): Invoice
    {
        if (! $staff->can('invoices.void')) {
            abort(403);
        }

        if (! $invoice->canVoid()) {
            throw ValidationException::withMessages([
                'invoice' => __('admin.invoices.cannot_void'),
            ]);
        }

        if ($invoice->order_id !== null) {
            $order = Order::query()->whereKey($invoice->order_id)->firstOrFail();

            return $this->cancelUnpaidOrder->handle($order, UnpaidOrderCancelSource::Staff, $staff)->invoice
                ?? throw new RuntimeException('Voided invoice disappeared.');
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

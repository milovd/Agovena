<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarkInvoicePaid
{
    public function handle(Invoice $invoice, Order $order): Invoice
    {
        return DB::transaction(function () use ($invoice, $order): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Paid) {
                return $locked->load('items');
            }

            if ($locked->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'invoice' => __('admin.invoices.cannot_pay_void'),
                ]);
            }

            $locked->status = InvoiceStatus::Paid;
            $locked->paid_at = $order->payment->paid_at ?? now();
            $locked->save();

            return $locked->load('items');
        });
    }
}

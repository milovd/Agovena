<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

final class AssertInvoiceCanBePaid
{
    public function handle(Order $order): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_pay_cancelled'),
            ]);
        }

        $invoice = $order->relationLoaded('invoice')
            ? $order->invoice
            : Invoice::query()->where('order_id', $order->id)->first();

        if ($invoice !== null && $invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages([
                'invoice' => __('admin.invoices.cannot_pay_void'),
            ]);
        }
    }
}

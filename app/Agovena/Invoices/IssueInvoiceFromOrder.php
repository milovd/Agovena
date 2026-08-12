<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Settings\SettingsRepository;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class IssueInvoiceFromOrder
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly SettingsRepository $settings,
    ) {}

    public function handle(Order $order): Invoice
    {
        $existing = Invoice::query()->where('order_id', $order->id)->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $order->loadMissing('items', 'payment');

        return DB::transaction(function () use ($order): Invoice {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing->load('items');
            }

            $status = InvoiceStatus::Issued;
            if ($locked->payment?->status === PaymentStatus::Paid) {
                $status = InvoiceStatus::Paid;
            }

            $invoice = Invoice::query()->create([
                'number' => $this->numbers->next(),
                'status' => $status,
                'order_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'customer_name' => $locked->customer_name,
                'customer_email' => $locked->customer_email,
                'billing_name' => $locked->billing_name,
                'billing_company' => $locked->billing_company,
                'billing_line1' => $locked->billing_line1,
                'billing_line2' => $locked->billing_line2,
                'billing_city' => $locked->billing_city,
                'billing_region' => $locked->billing_region,
                'billing_postal_code' => $locked->billing_postal_code,
                'billing_country' => $locked->billing_country,
                'billing_phone' => $locked->billing_phone,
                'merchant_name' => (string) $this->settings->get('general', 'site_name', config('app.name')),
                'merchant_address' => null,
                'issued_at' => now()->toDateString(),
                'due_at' => null,
                'subtotal_amount' => $locked->subtotal_amount,
                'discount_amount' => $locked->discount_amount,
                'tax_amount' => $locked->tax_amount,
                'total_amount' => $locked->total_amount,
                'currency' => $locked->currency,
            ]);

            foreach ($locked->items as $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'label' => $item->label,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'line_total_amount' => $item->line_total_amount,
                    'currency' => $item->currency,
                ]);
            }

            if ((int) $locked->shipping_amount > 0) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'label' => $locked->shipping_method_label ?: __('common.shipping'),
                    'quantity' => 1,
                    'unit_amount' => (int) $locked->shipping_amount,
                    'line_total_amount' => (int) $locked->shipping_amount,
                    'currency' => $locked->currency,
                ]);
            }

            if ((int) $locked->discount_amount > 0) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'label' => __('common.discount').' '.($locked->discount_code ?: ''),
                    'quantity' => 1,
                    'unit_amount' => (int) $locked->discount_amount,
                    'line_total_amount' => (int) $locked->discount_amount,
                    'currency' => $locked->currency,
                ]);
            }

            $exclusiveTax = (int) $locked->total_amount
                > ((int) $locked->subtotal_amount - (int) $locked->discount_amount + (int) $locked->shipping_amount);
            if ($exclusiveTax && (int) $locked->tax_amount > 0) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'label' => $locked->tax_rate_name ?: __('common.tax'),
                    'quantity' => 1,
                    'unit_amount' => (int) $locked->tax_amount,
                    'line_total_amount' => (int) $locked->tax_amount,
                    'currency' => $locked->currency,
                ]);
            }

            return $invoice->load('items');
        });
    }
}

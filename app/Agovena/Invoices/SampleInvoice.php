<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Settings\SettingsRepository;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;

/**
 * Unsaved invoice with fictional buyer data for previewing invoice templates.
 * Only the seller identity comes from the store settings, as on a real invoice.
 */
final class SampleInvoice
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function make(): Invoice
    {
        $currency = strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR'));
        $sellerName = trim((string) $this->settings->get('store', 'seller_name', ''));
        $sellerAddress = trim((string) $this->settings->get('store', 'seller_address', ''));
        $sellerVatNumber = trim((string) $this->settings->get('store', 'seller_vat_number', ''));
        $sellerCompanyNumber = trim((string) $this->settings->get('store', 'seller_company_number', ''));

        $invoice = new Invoice([
            'number' => 'PREVIEW-0001',
            'status' => InvoiceStatus::Paid,
            'customer_name' => __('invoices.preview.customer'),
            'customer_email' => 'customer@example.com',
            'billing_name' => __('invoices.preview.customer'),
            'billing_company' => __('invoices.preview.company'),
            'billing_line1' => __('invoices.preview.street'),
            'billing_city' => __('invoices.preview.city'),
            'billing_postal_code' => '1000 AA',
            'billing_country' => 'NL',
            'merchant_name' => $sellerName !== ''
                ? $sellerName
                : (string) $this->settings->get('general', 'site_name', config('app.name')),
            'merchant_address' => $sellerAddress !== '' ? $sellerAddress : null,
            'merchant_vat_number' => $sellerVatNumber !== '' ? $sellerVatNumber : null,
            'merchant_company_number' => $sellerCompanyNumber !== '' ? $sellerCompanyNumber : null,
            'custom_properties_snapshot' => [
                ['key' => 'vat_number', 'label' => __('invoices.preview.vat_label'), 'value' => 'NL000000000B00'],
            ],
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'paid_at' => now(),
            'subtotal_amount' => 14000,
            'discount_amount' => 1000,
            'tax_amount' => 2730,
            'tax_rate_name' => __('invoices.preview.tax_name'),
            'tax_rate_bps' => 2100,
            'total_amount' => 15730,
            'currency' => $currency,
        ]);

        $invoice->setRelation('order', (new Order)->forceFill(['number' => 'ORD-10042']));
        $invoice->setRelation('items', collect([
            $this->item(InvoiceItemKind::Product, __('invoices.preview.item'), 2, 5000, $currency, [
                ['key' => 'size', 'label' => __('invoices.preview.option_label'), 'display' => __('invoices.preview.option_value')],
            ]),
            $this->item(InvoiceItemKind::Product, __('invoices.preview.service'), 1, 4000, $currency),
            $this->item(InvoiceItemKind::Discount, __('invoices.preview.discount'), 1, 1000, $currency),
        ]));

        return $invoice;
    }

    /** Unsaved credit note for the first product line of the sample invoice. */
    public function makeCreditNote(): CreditNote
    {
        $invoice = $this->make();
        $currency = (string) $invoice->currency;

        $creditNote = new CreditNote([
            'number' => 'PREVIEW-CN-0001',
            'status' => CreditNoteStatus::Issued,
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'billing_name' => $invoice->billing_name,
            'billing_company' => $invoice->billing_company,
            'billing_line1' => $invoice->billing_line1,
            'billing_city' => $invoice->billing_city,
            'billing_postal_code' => $invoice->billing_postal_code,
            'billing_country' => $invoice->billing_country,
            'merchant_name' => $invoice->merchant_name,
            'merchant_address' => $invoice->merchant_address,
            'merchant_vat_number' => $invoice->merchant_vat_number,
            'merchant_company_number' => $invoice->merchant_company_number,
            'custom_properties_snapshot' => $invoice->custom_properties_snapshot,
            'issued_at' => now()->toDateString(),
            'reason' => __('invoices.preview.credit_reason'),
            'subtotal_amount' => 5000,
            'tax_amount' => 1050,
            'tax_rate_name' => $invoice->tax_rate_name,
            'tax_rate_bps' => $invoice->tax_rate_bps,
            'total_amount' => 6050,
            'currency' => $currency,
        ]);
        $creditNote->setRelation('invoice', $invoice);
        $creditNote->setRelation('items', collect([
            new CreditNoteItem([
                'kind' => InvoiceItemKind::Product,
                'label' => __('invoices.preview.item'),
                'quantity' => 1,
                'unit_amount' => 5000,
                'line_total_amount' => 5000,
                'currency' => $currency,
            ]),
        ]));

        return $creditNote;
    }

    /** @param list<array<string, string>> $options */
    private function item(InvoiceItemKind $kind, string $label, int $quantity, int $unitAmount, string $currency, array $options = []): InvoiceItem
    {
        return new InvoiceItem([
            'kind' => $kind,
            'label' => $label,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'line_total_amount' => $quantity * $unitAmount,
            'currency' => $currency,
            'options_snapshot' => $options === [] ? null : $options,
        ]);
    }
}

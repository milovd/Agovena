<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Enums\InvoiceItemKind;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\MoneyFormatter;

/**
 * Display-ready snapshot of an invoice or credit note. Theme templates render
 * only these values, so every layout shows the same legally relevant data.
 */
final readonly class InvoiceDocumentData
{
    /**
     * @param  list<array{label: string, value: string}>  $buyerProperties
     * @param  list<array{label: string, options: list<array{label: string, value: string}>, quantity: int, unitAmount: string, amount: string}>  $lines
     */
    public function __construct(
        public bool $isCreditNote,
        public string $documentTitle,
        public string $number,
        public ?string $orderNumber,
        public string $statusLabel,
        public ?string $issuedOn,
        public ?string $dueOn,
        public ?string $paidOn,
        public ?string $relatedInvoiceNumber,
        public ?string $reason,
        public string $currency,
        public string $sellerName,
        public ?string $sellerAddress,
        public ?string $sellerVatNumber,
        public ?string $sellerCompanyNumber,
        public string $buyerName,
        public ?string $buyerCompany,
        public ?string $buyerLine1,
        public ?string $buyerLine2,
        public string $buyerPostalCity,
        public ?string $buyerRegion,
        public ?string $buyerCountry,
        public ?string $buyerPhone,
        public string $buyerEmail,
        public array $buyerProperties,
        public array $lines,
        public string $subtotal,
        public ?string $discount,
        public ?string $credit,
        public string $taxLabel,
        public string $tax,
        public string $total,
        public string $netTotal,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $currency = (string) $invoice->currency;

        return new self(
            isCreditNote: false,
            documentTitle: __('invoices.document_title'),
            number: (string) $invoice->number,
            orderNumber: self::orderNumber($invoice),
            statusLabel: __('invoices.status.'.$invoice->status->value),
            issuedOn: $invoice->issued_at?->format('Y-m-d'),
            dueOn: $invoice->due_at?->format('Y-m-d'),
            paidOn: $invoice->paid_at?->format('Y-m-d'),
            relatedInvoiceNumber: null,
            reason: null,
            currency: $currency,
            sellerName: (string) $invoice->merchant_name,
            sellerAddress: self::filled($invoice->merchant_address),
            sellerVatNumber: self::filled($invoice->merchant_vat_number),
            sellerCompanyNumber: self::filled($invoice->merchant_company_number),
            buyerName: (string) ($invoice->billing_name ?: $invoice->customer_name),
            buyerCompany: self::filled($invoice->billing_company),
            buyerLine1: self::filled($invoice->billing_line1),
            buyerLine2: self::filled($invoice->billing_line2),
            buyerPostalCity: trim($invoice->billing_postal_code.' '.$invoice->billing_city),
            buyerRegion: self::filled($invoice->billing_region),
            buyerCountry: self::filled($invoice->billing_country),
            buyerPhone: self::filled($invoice->billing_phone),
            buyerEmail: (string) $invoice->customer_email,
            buyerProperties: self::properties($invoice->custom_properties_snapshot),
            lines: $invoice->items->map(fn (InvoiceItem $item): array => self::line($item, $item->options_snapshot))->values()->all(),
            subtotal: MoneyFormatter::format((int) $invoice->subtotal_amount, $currency),
            discount: (int) $invoice->discount_amount > 0 ? '−'.MoneyFormatter::format((int) $invoice->discount_amount, $currency) : null,
            credit: (int) $invoice->credit_amount > 0 ? '−'.MoneyFormatter::format((int) $invoice->credit_amount, $currency) : null,
            taxLabel: self::taxLabel(
                $invoice->tax_rate_name,
                $invoice->tax_rate_bps,
                self::taxIncluded($invoice->items, (int) $invoice->tax_amount, (int) $invoice->total_amount, (int) $invoice->discount_amount, (int) $invoice->credit_amount),
            ),
            tax: MoneyFormatter::format((int) $invoice->tax_amount, $currency),
            total: MoneyFormatter::format((int) $invoice->total_amount, $currency),
            netTotal: MoneyFormatter::format((int) $invoice->total_amount - (int) $invoice->tax_amount, $currency),
        );
    }

    public static function fromCreditNote(CreditNote $creditNote): self
    {
        $currency = (string) $creditNote->currency;

        return new self(
            isCreditNote: true,
            documentTitle: __('credit_notes.document_title'),
            number: (string) $creditNote->number,
            orderNumber: null,
            statusLabel: __('credit_notes.document_title'),
            issuedOn: $creditNote->issued_at->format('Y-m-d'),
            dueOn: null,
            paidOn: null,
            relatedInvoiceNumber: $creditNote->invoice->number,
            reason: (string) $creditNote->reason,
            currency: $currency,
            sellerName: (string) $creditNote->merchant_name,
            sellerAddress: self::filled($creditNote->merchant_address),
            sellerVatNumber: self::filled($creditNote->merchant_vat_number),
            sellerCompanyNumber: self::filled($creditNote->merchant_company_number),
            buyerName: (string) ($creditNote->billing_name ?: $creditNote->customer_name),
            buyerCompany: self::filled($creditNote->billing_company),
            buyerLine1: self::filled($creditNote->billing_line1),
            buyerLine2: self::filled($creditNote->billing_line2),
            buyerPostalCity: trim($creditNote->billing_postal_code.' '.$creditNote->billing_city),
            buyerRegion: self::filled($creditNote->billing_region),
            buyerCountry: self::filled($creditNote->billing_country),
            buyerPhone: self::filled($creditNote->billing_phone),
            buyerEmail: (string) $creditNote->customer_email,
            buyerProperties: self::properties($creditNote->custom_properties_snapshot),
            lines: $creditNote->items->map(fn (CreditNoteItem $item): array => self::line($item, null))->values()->all(),
            subtotal: MoneyFormatter::format((int) $creditNote->subtotal_amount, $currency),
            discount: null,
            credit: null,
            taxLabel: self::taxLabel(
                $creditNote->tax_rate_name,
                $creditNote->tax_rate_bps,
                self::taxIncluded($creditNote->items, (int) $creditNote->tax_amount, (int) $creditNote->total_amount, 0, 0),
            ),
            tax: MoneyFormatter::format((int) $creditNote->tax_amount, $currency),
            total: MoneyFormatter::format((int) $creditNote->total_amount, $currency),
            netTotal: MoneyFormatter::format((int) $creditNote->total_amount - (int) $creditNote->tax_amount, $currency),
        );
    }

    /**
     * Dated references shown next to the number: issue date, then the payment date or the credited invoice.
     *
     * @return list<array{label: string, value: string}>
     */
    public function references(): array
    {
        $rows = [['label' => __('invoices.issued'), 'value' => (string) $this->issuedOn]];

        if ($this->dueOn !== null) {
            $rows[] = ['label' => __('invoices.due_on'), 'value' => $this->dueOn];
        }

        if ($this->paidOn !== null) {
            $rows[] = ['label' => __('invoices.paid_on'), 'value' => $this->paidOn];
        }

        if ($this->relatedInvoiceNumber !== null) {
            $rows[] = ['label' => __('credit_notes.related_invoice'), 'value' => $this->relatedInvoiceNumber];
        }

        return $rows;
    }

    /**
     * Non-empty postal address lines below the name and company, in display order.
     *
     * @return list<string>
     */
    public function buyerAddressLines(): array
    {
        if ($this->buyerLine1 === null) {
            return [];
        }

        return array_values(array_filter(
            [$this->buyerLine1, $this->buyerLine2, $this->buyerPostalCity, $this->buyerRegion, $this->buyerCountry],
            static fn (?string $line): bool => $line !== null && $line !== '',
        ));
    }

    public function isPaid(): bool
    {
        return $this->paidOn !== null;
    }

    /**
     * Seller identifiers below the seller address, labelled, in display order.
     *
     * @return list<array{label: string, value: string}>
     */
    public function sellerIdentifiers(): array
    {
        $rows = [];

        if ($this->sellerVatNumber !== null) {
            $rows[] = ['label' => __('invoices.seller_vat_number'), 'value' => $this->sellerVatNumber];
        }

        if ($this->sellerCompanyNumber !== null) {
            $rows[] = ['label' => __('invoices.seller_company_number'), 'value' => $this->sellerCompanyNumber];
        }

        return $rows;
    }

    /** @return array{label: string, options: list<array{label: string, value: string}>, quantity: int, unitAmount: string, amount: string} */
    private static function line(InvoiceItem|CreditNoteItem $item, mixed $options): array
    {
        $kind = $item->kind instanceof InvoiceItemKind ? $item->kind : InvoiceItemKind::Product;
        $sign = $kind->isAdjustment() ? '−' : '';

        return [
            'label' => (string) $item->label,
            'options' => self::options($options),
            'quantity' => (int) $item->quantity,
            'unitAmount' => $sign.MoneyFormatter::format((int) $item->unit_amount, $item->currency),
            'amount' => $sign.MoneyFormatter::format((int) $item->line_total_amount, $item->currency),
        ];
    }

    private static function orderNumber(Invoice $invoice): ?string
    {
        $order = $invoice->relationLoaded('order') ? $invoice->getRelation('order') : null;
        if ($order === null && $invoice->order_id !== null) {
            $order = $invoice->order()->first(['id', 'number']);
        }

        return $order === null ? null : self::filled($order->number);
    }

    /** Tax rate name with its percentage, marked as included when the prices already contained it. */
    private static function taxLabel(?string $name, ?int $rateBps, bool $included): string
    {
        $label = $name !== null && $name !== '' ? $name : __('common.tax');

        if ($rateBps !== null && ! str_contains($label, '%')) {
            $decimal = str_starts_with(app()->getLocale(), 'en') ? '.' : ',';
            $percentage = rtrim(rtrim(number_format($rateBps / 100, 2, $decimal, ''), '0'), $decimal);
            $label .= ' '.$percentage.'%';
        }

        return $included ? __('invoices.tax_included', ['label' => $label]) : $label;
    }

    /**
     * Whether the tax was already part of the line prices. Documents do not store the price mode, so it is
     * read from their own amounts: with tax added on top the total exceeds the lines by the tax amount.
     *
     * @param  iterable<InvoiceItem|CreditNoteItem>  $items
     */
    private static function taxIncluded(iterable $items, int $tax, int $total, int $discount, int $credit): bool
    {
        if ($tax <= 0) {
            return false;
        }

        $lines = 0;
        $hasDiscountLine = false;
        $hasCreditLine = false;
        foreach ($items as $item) {
            $kind = $item->kind instanceof InvoiceItemKind ? $item->kind : InvoiceItemKind::Product;
            if ($kind === InvoiceItemKind::Tax) {
                continue;
            }
            $hasDiscountLine = $hasDiscountLine || $kind === InvoiceItemKind::Discount;
            $hasCreditLine = $hasCreditLine || $kind === InvoiceItemKind::Credit;
            $lines += $kind->isAdjustment() ? -(int) $item->line_total_amount : (int) $item->line_total_amount;
        }

        if (! $hasDiscountLine) {
            $lines -= $discount;
        }
        if (! $hasCreditLine) {
            $lines -= $credit;
        }

        return $total === $lines;
    }

    /** @return list<array{label: string, value: string}> */
    private static function options(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $out = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $out[] = [
                'label' => (string) ($option['label'] ?? $option['key'] ?? ''),
                'value' => (string) ($option['display'] ?? $option['value'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<array{label: string, value: string}> */
    private static function properties(mixed $properties): array
    {
        if (! is_array($properties)) {
            return [];
        }

        $out = [];
        foreach ($properties as $property) {
            if (! is_array($property)) {
                continue;
            }
            $out[] = [
                'label' => (string) ($property['label'] ?? $property['key'] ?? ''),
                'value' => (string) ($property['value'] ?? ''),
            ];
        }

        return $out;
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

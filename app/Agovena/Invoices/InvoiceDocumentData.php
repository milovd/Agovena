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
     * @param  list<array{label: string, options: list<array{label: string, value: string}>, quantity: int, amount: string}>  $lines
     */
    public function __construct(
        public bool $isCreditNote,
        public string $documentTitle,
        public string $number,
        public string $statusLabel,
        public ?string $issuedOn,
        public ?string $paidOn,
        public ?string $relatedInvoiceNumber,
        public ?string $reason,
        public string $currency,
        public string $sellerName,
        public ?string $sellerAddress,
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
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $currency = (string) $invoice->currency;

        return new self(
            isCreditNote: false,
            documentTitle: __('invoices.document_title'),
            number: (string) $invoice->number,
            statusLabel: __('invoices.status.'.$invoice->status->value),
            issuedOn: $invoice->issued_at?->format('Y-m-d'),
            paidOn: $invoice->paid_at?->format('Y-m-d'),
            relatedInvoiceNumber: null,
            reason: null,
            currency: $currency,
            sellerName: (string) $invoice->merchant_name,
            sellerAddress: self::filled($invoice->merchant_address),
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
            taxLabel: $invoice->tax_rate_name ?: __('common.tax'),
            tax: MoneyFormatter::format((int) $invoice->tax_amount, $currency),
            total: MoneyFormatter::format((int) $invoice->total_amount, $currency),
        );
    }

    public static function fromCreditNote(CreditNote $creditNote): self
    {
        $currency = (string) $creditNote->currency;

        return new self(
            isCreditNote: true,
            documentTitle: __('credit_notes.document_title'),
            number: (string) $creditNote->number,
            statusLabel: __('credit_notes.document_title'),
            issuedOn: $creditNote->issued_at->format('Y-m-d'),
            paidOn: null,
            relatedInvoiceNumber: $creditNote->invoice->number,
            reason: (string) $creditNote->reason,
            currency: $currency,
            sellerName: (string) $creditNote->merchant_name,
            sellerAddress: self::filled($creditNote->merchant_address),
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
            taxLabel: $creditNote->tax_rate_name ?: __('common.tax'),
            tax: MoneyFormatter::format((int) $creditNote->tax_amount, $currency),
            total: MoneyFormatter::format((int) $creditNote->total_amount, $currency),
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

    /** @return array{label: string, options: list<array{label: string, value: string}>, quantity: int, amount: string} */
    private static function line(InvoiceItem|CreditNoteItem $item, mixed $options): array
    {
        $kind = $item->kind instanceof InvoiceItemKind ? $item->kind : InvoiceItemKind::Product;
        $amount = MoneyFormatter::format((int) $item->line_total_amount, $item->currency);

        return [
            'label' => (string) $item->label,
            'options' => self::options($options),
            'quantity' => (int) $item->quantity,
            'amount' => $kind->isAdjustment() ? '−'.$amount : $amount,
        ];
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

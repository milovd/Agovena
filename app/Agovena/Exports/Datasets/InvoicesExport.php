<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invoices followed by credit notes, told apart by document_type. Credit notes
 * reference the invoice they correct in credited_invoice_number.
 */
final class InvoicesExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'invoices';
    }

    public function permission(): string
    {
        return 'invoices.view';
    }

    public function recordElement(): string
    {
        return 'document';
    }

    public function columns(): array
    {
        return [
            'document_type',
            'id',
            'number',
            'status',
            'credited_invoice_number',
            'order_number',
            'customer_id',
            'customer_email',
            'customer_name',
            'billing_company',
            'billing_country',
            'currency',
            'subtotal',
            'discount',
            'credit',
            'tax',
            'payment_fee',
            'total',
            'tax_rate_name',
            'reason',
            'issued_at',
            'due_at',
            'paid_at',
            'created_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Invoice::query())->count()
            + $filters->apply(CreditNote::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Invoice> $invoices */
        $invoices = $filters->apply(Invoice::query())->with('order:id,number');

        foreach ($invoices->lazyById(500, 'invoices.id', 'id') as $invoice) {
            /** @var Invoice $invoice */
            $currency = (string) $invoice->getAttribute('currency');

            yield [
                'document_type' => 'invoice',
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $this->values->enum($invoice->status),
                'credited_invoice_number' => null,
                'order_number' => $invoice->order?->number,
                'customer_id' => $invoice->customer_id,
                'customer_email' => $this->string($invoice->getAttribute('customer_email')),
                'customer_name' => $this->string($invoice->getAttribute('customer_name')),
                'billing_company' => $this->string($invoice->getAttribute('billing_company')),
                'billing_country' => $this->string($invoice->getAttribute('billing_country')),
                'currency' => $currency,
                'subtotal' => $this->values->money((int) $invoice->getAttribute('subtotal_amount'), $currency),
                'discount' => $this->values->money((int) $invoice->getAttribute('discount_amount'), $currency),
                'credit' => $this->values->money((int) $invoice->getAttribute('credit_amount'), $currency),
                'tax' => $this->values->money((int) $invoice->getAttribute('tax_amount'), $currency),
                'payment_fee' => $this->values->money($invoice->payment_fee_amount, $currency),
                'total' => $this->values->money((int) $invoice->getAttribute('total_amount'), $currency),
                'tax_rate_name' => $this->string($invoice->getAttribute('tax_rate_name')),
                'reason' => null,
                'issued_at' => $this->values->date($invoice->issued_at),
                'due_at' => $this->values->date($invoice->due_at),
                'paid_at' => $this->values->dateTime($invoice->paid_at),
                'created_at' => $this->values->dateTime($invoice->created_at),
            ];
        }

        /** @var Builder<CreditNote> $creditNotes */
        $creditNotes = $filters->apply(CreditNote::query())->with(['invoice:id,number', 'order:id,number']);

        foreach ($creditNotes->lazyById(500, 'credit_notes.id', 'id') as $creditNote) {
            /** @var CreditNote $creditNote */
            $currency = (string) $creditNote->getAttribute('currency');

            yield [
                'document_type' => 'credit_note',
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'status' => $this->values->enum($creditNote->status),
                'credited_invoice_number' => $creditNote->invoice->number,
                'order_number' => $creditNote->order?->number,
                'customer_id' => $creditNote->customer_id,
                'customer_email' => $this->string($creditNote->getAttribute('customer_email')),
                'customer_name' => $this->string($creditNote->getAttribute('customer_name')),
                'billing_company' => $this->string($creditNote->getAttribute('billing_company')),
                'billing_country' => $this->string($creditNote->getAttribute('billing_country')),
                'currency' => $currency,
                'subtotal' => $this->values->money((int) $creditNote->getAttribute('subtotal_amount'), $currency),
                'discount' => null,
                'credit' => null,
                'tax' => $this->values->money((int) $creditNote->getAttribute('tax_amount'), $currency),
                'payment_fee' => null,
                'total' => $this->values->money((int) $creditNote->getAttribute('total_amount'), $currency),
                'tax_rate_name' => $this->string($creditNote->getAttribute('tax_rate_name')),
                'reason' => $this->string($creditNote->getAttribute('reason')),
                'issued_at' => $this->values->date($creditNote->issued_at),
                'due_at' => null,
                'paid_at' => null,
                'created_at' => $this->values->dateTime($creditNote->created_at),
            ];
        }
    }

    private function string(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

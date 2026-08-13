<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Events\CreditNoteIssued;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueCreditNote
{
    public function __construct(
        private readonly CreditNoteNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, int>|null  $quantities  invoice_item_id => quantity; null issues the remaining invoice total
     */
    public function handle(Invoice $invoice, User $staff, string $reason, ?array $quantities = null): CreditNote
    {
        if (! $staff->can('invoices.credit')) {
            abort(403);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('admin.credit_notes.reason_required'),
            ]);
        }

        return DB::transaction(function () use ($invoice, $staff, $reason, $quantities): CreditNote {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $locked->load('items');

            if ($locked->status !== InvoiceStatus::Paid) {
                throw ValidationException::withMessages([
                    'invoice' => __('admin.credit_notes.requires_paid_invoice'),
                ]);
            }

            $remaining = $locked->remainingCreditable();
            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => __('admin.credit_notes.nothing_remaining'),
                ]);
            }

            $allocation = $quantities === null
                ? $this->allocateFull($locked, $remaining)
                : $this->allocatePartial($locked, $quantities, $remaining);

            $creditNote = CreditNote::query()->create([
                'number' => $this->numbers->next(),
                'status' => CreditNoteStatus::Issued,
                'invoice_id' => $locked->id,
                'order_id' => $locked->order_id,
                'customer_id' => $locked->customer_id,
                'created_by' => $staff->id,
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
                'merchant_name' => $locked->merchant_name,
                'merchant_address' => $locked->merchant_address,
                'issued_at' => now()->toDateString(),
                'reason' => $reason,
                'subtotal_amount' => $allocation['subtotal'],
                'tax_amount' => $allocation['tax'],
                'total_amount' => $allocation['total'],
                'tax_rate_name' => $locked->tax_rate_name,
                'tax_rate_bps' => $locked->tax_rate_bps,
                'currency' => $locked->currency,
            ]);

            foreach ($allocation['items'] as $item) {
                CreditNoteItem::query()->create([
                    'credit_note_id' => $creditNote->id,
                    'invoice_item_id' => $item['invoice_item_id'],
                    'kind' => $item['kind'],
                    'label' => $item['label'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'line_total_amount' => $item['line_total_amount'],
                    'currency' => $locked->currency,
                ]);
            }

            $creditNote->load('items');

            $this->audit->log('credit_note.issued', $creditNote, [
                'invoice_id' => $locked->id,
                'invoice_number' => $locked->number,
                'total_amount' => $creditNote->total_amount,
                'full' => $quantities === null,
            ]);

            event(new CreditNoteIssued($creditNote));

            return $creditNote;
        });
    }

    /**
     * @return array{items: list<array{invoice_item_id: int|null, kind: InvoiceItemKind, label: string, quantity: int, unit_amount: int, line_total_amount: int}>, subtotal: int, tax: int, total: int}
     */
    private function allocateFull(Invoice $invoice, int $remaining): array
    {
        $items = [];
        $subtotal = 0;

        foreach ($invoice->items as $item) {
            if (! $this->isCreditableKind($item->kind)) {
                continue;
            }

            $qty = $invoice->remainingQuantityFor($item);
            if ($qty <= 0) {
                continue;
            }

            $lineTotal = $item->unit_amount * $qty;
            $items[] = [
                'invoice_item_id' => $item->id,
                'kind' => $item->kind,
                'label' => $item->label,
                'quantity' => $qty,
                'unit_amount' => $item->unit_amount,
                'line_total_amount' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        $tax = $this->taxFor($invoice, $subtotal, remainingCap: $remaining);
        $gross = $this->hasExclusiveTax($invoice) ? $subtotal + $tax : $subtotal;

        if ($gross > $remaining) {
            $items[] = [
                'invoice_item_id' => null,
                'kind' => InvoiceItemKind::Discount,
                'label' => __('admin.credit_notes.balance_adjustment'),
                'quantity' => 1,
                'unit_amount' => $gross - $remaining,
                'line_total_amount' => $gross - $remaining,
            ];
            $gross = $remaining;
        } elseif ($gross < $remaining) {
            $items[] = [
                'invoice_item_id' => null,
                'kind' => InvoiceItemKind::Credit,
                'label' => __('admin.credit_notes.remaining_balance'),
                'quantity' => 1,
                'unit_amount' => $remaining - $gross,
                'line_total_amount' => $remaining - $gross,
            ];
            $gross = $remaining;
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $this->hasExclusiveTax($invoice) ? $tax : $this->inclusiveTaxDisplay($invoice, $remaining),
            'total' => $gross,
        ];
    }

    /**
     * @param  array<int, int>  $quantities
     * @return array{items: list<array{invoice_item_id: int|null, kind: InvoiceItemKind, label: string, quantity: int, unit_amount: int, line_total_amount: int}>, subtotal: int, tax: int, total: int}
     */
    private function allocatePartial(Invoice $invoice, array $quantities, int $remaining): array
    {
        $items = [];
        $subtotal = 0;

        foreach ($quantities as $invoiceItemId => $quantity) {
            $invoiceItemId = (int) $invoiceItemId;
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            $item = $invoice->items->firstWhere('id', $invoiceItemId);
            if (! $item instanceof InvoiceItem) {
                throw ValidationException::withMessages([
                    'quantities' => __('admin.credit_notes.unknown_line'),
                ]);
            }

            if (! $this->isCreditableKind($item->kind)) {
                throw ValidationException::withMessages([
                    'quantities' => __('admin.credit_notes.line_not_creditable'),
                ]);
            }

            $available = $invoice->remainingQuantityFor($item);
            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantities' => __('admin.credit_notes.quantity_exceeds', [
                        'label' => $item->label,
                        'available' => $available,
                    ]),
                ]);
            }

            $lineTotal = $item->unit_amount * $quantity;
            $items[] = [
                'invoice_item_id' => $item->id,
                'kind' => $item->kind,
                'label' => $item->label,
                'quantity' => $quantity,
                'unit_amount' => $item->unit_amount,
                'line_total_amount' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'quantities' => __('admin.credit_notes.select_lines'),
            ]);
        }

        $tax = $this->taxFor($invoice, $subtotal, remainingCap: $remaining);
        $total = $this->hasExclusiveTax($invoice) ? $subtotal + $tax : $subtotal;

        if ($total > $remaining) {
            throw ValidationException::withMessages([
                'quantities' => __('admin.credit_notes.exceeds_remaining'),
            ]);
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $this->hasExclusiveTax($invoice) ? $tax : $this->inclusiveTaxDisplay($invoice, $total),
            'total' => $total,
        ];
    }

    private function taxFor(Invoice $invoice, int $creditedNet, int $remainingCap): int
    {
        if (! $this->hasExclusiveTax($invoice) || (int) $invoice->tax_amount <= 0) {
            return 0;
        }

        $originalNet = $this->originalNet($invoice);
        $alreadyTax = (int) $invoice->creditNotes()->sum('tax_amount');
        $remainingTax = max(0, (int) $invoice->tax_amount - $alreadyTax);

        if ($originalNet <= 0 || $creditedNet <= 0) {
            return 0;
        }

        $share = (int) floor($creditedNet * (int) $invoice->tax_amount / $originalNet);
        $share = min($share, $remainingTax);

        $exclusiveTotal = $creditedNet + $share;
        if ($exclusiveTotal > $remainingCap) {
            $share = max(0, $remainingCap - $creditedNet);
        }

        return $share;
    }

    private function inclusiveTaxDisplay(Invoice $invoice, int $creditedTotal): int
    {
        if ((int) $invoice->tax_amount <= 0 || (int) $invoice->total_amount <= 0) {
            return 0;
        }

        return (int) floor($creditedTotal * (int) $invoice->tax_amount / (int) $invoice->total_amount);
    }

    private function originalNet(Invoice $invoice): int
    {
        return (int) $invoice->items
            ->filter(fn (InvoiceItem $item): bool => $this->isCreditableKind($item->kind))
            ->sum(fn (InvoiceItem $item): int => (int) $item->line_total_amount);
    }

    private function hasExclusiveTax(Invoice $invoice): bool
    {
        if ($invoice->items->contains(fn (InvoiceItem $item): bool => $item->kind === InvoiceItemKind::Tax)) {
            return true;
        }

        $shipping = (int) $invoice->items
            ->filter(fn (InvoiceItem $item): bool => $item->kind === InvoiceItemKind::Shipping)
            ->sum(fn (InvoiceItem $item): int => (int) $item->line_total_amount);

        return (int) $invoice->total_amount
            > ((int) $invoice->subtotal_amount - (int) $invoice->discount_amount - (int) $invoice->credit_amount + $shipping);
    }

    private function isCreditableKind(InvoiceItemKind $kind): bool
    {
        return in_array($kind, [InvoiceItemKind::Product, InvoiceItemKind::Shipping], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;
        $invoice->loadMissing(['items', 'creditNotes']);

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'currency' => $invoice->currency,
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'total_formatted' => MoneyFormatter::format($invoice->total_amount, $invoice->currency),
            'credited_amount' => $invoice->creditedAmount(),
            'items' => $invoice->items->map(static fn ($item): array => [
                'label' => $item->label,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'line_total_amount' => $item->line_total_amount,
            ])->values()->all(),
            'credit_notes' => $invoice->creditNotes->map(static fn ($note): array => [
                'id' => $note->id,
                'number' => $note->number,
                'total_amount' => $note->total_amount,
            ])->values()->all(),
        ];
    }
}

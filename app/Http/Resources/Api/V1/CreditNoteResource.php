<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CreditNote;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
final class CreditNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CreditNote $note */
        $note = $this->resource;
        $note->loadMissing('items');

        return [
            'id' => $note->id,
            'number' => $note->number,
            'status' => $note->status->value,
            'reason' => $note->reason,
            'issued_at' => $note->issued_at->toDateString(),
            'invoice_id' => $note->invoice_id,
            'currency' => $note->currency,
            'subtotal_amount' => $note->subtotal_amount,
            'tax_amount' => $note->tax_amount,
            'total_amount' => $note->total_amount,
            'total_formatted' => MoneyFormatter::format($note->total_amount, $note->currency),
            'items' => $note->items->map(static fn ($item): array => [
                'label' => $item->label,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'line_total_amount' => $item->line_total_amount,
            ])->values()->all(),
        ];
    }
}

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
            'customer' => [
                'id' => $note->customer_id,
                'name' => $note->customer_name,
                'email' => $note->customer_email,
            ],
            'billing' => [
                'name' => $note->billing_name,
                'company' => $note->billing_company,
                'line1' => $note->billing_line1,
                'line2' => $note->billing_line2,
                'city' => $note->billing_city,
                'region' => $note->billing_region,
                'postal_code' => $note->billing_postal_code,
                'country' => $note->billing_country,
                'phone' => $note->billing_phone,
            ],
            'custom_properties' => $note->custom_properties_snapshot ?? [],
            'custom_properties_snapshot' => $note->custom_properties_snapshot ?? [],
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

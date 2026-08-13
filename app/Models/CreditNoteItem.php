<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceItemKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'credit_note_id',
    'invoice_item_id',
    'kind',
    'label',
    'quantity',
    'unit_amount',
    'line_total_amount',
    'currency',
])]
class CreditNoteItem extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => InvoiceItemKind::class,
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'line_total_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @return BelongsTo<InvoiceItem, $this> */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}

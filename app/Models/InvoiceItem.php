<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceItemKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'kind',
    'label',
    'quantity',
    'unit_amount',
    'line_total_amount',
    'currency',
    'options_snapshot',
])]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => InvoiceItemKind::class,
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'line_total_amount' => 'integer',
            'options_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

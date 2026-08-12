<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_product_id',
    'to_product_id',
    'change_type',
    'is_active',
    'timing',
    'sort',
])]
class ProductPlanChange extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function fromProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'from_product_id');
    }

    public function toProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'to_product_id');
    }
}

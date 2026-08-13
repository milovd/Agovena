<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_option_id
 * @property string $value
 * @property string $label
 * @property int $price_adjustment_amount
 * @property int $sort
 * @property bool $is_active
 */
#[Fillable([
    'product_option_id',
    'value',
    'label',
    'price_adjustment_amount',
    'sort',
    'is_active',
])]
class ProductOptionChoice extends Model
{
    protected function casts(): array
    {
        return [
            'price_adjustment_amount' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProductOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}

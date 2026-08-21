<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional manual catalog price for a product in a specific currency.
 *
 * @property int $id
 * @property int $product_id
 * @property string $currency
 * @property int $price_amount
 */
#[Fillable(['product_id', 'currency', 'price_amount'])]
class ProductCurrencyPrice extends Model
{
    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

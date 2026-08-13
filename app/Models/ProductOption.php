<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductOptionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable purchase input. Distinct from variants (SKU/inventory).
 *
 * @property int $id
 * @property int $product_id
 * @property string $key
 * @property string $label
 * @property ProductOptionType $type
 * @property bool $is_required
 * @property bool $is_active
 * @property int $sort
 * @property int $price_adjustment_amount
 * @property array<string, mixed>|null $constraints
 * @property-read Collection<int, ProductOptionChoice> $choices
 */
#[Fillable([
    'product_id',
    'key',
    'label',
    'type',
    'is_required',
    'is_active',
    'sort',
    'price_adjustment_amount',
    'constraints',
])]
class ProductOption extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ProductOptionType::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'price_adjustment_amount' => 'integer',
            'constraints' => 'array',
        ];
    }

    /** @param Builder<ProductOption> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<ProductOption> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductOptionChoice, $this> */
    public function choices(): HasMany
    {
        return $this->hasMany(ProductOptionChoice::class)->orderBy('sort')->orderBy('id');
    }
}

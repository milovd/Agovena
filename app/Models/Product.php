<?php

declare(strict_types=1);

namespace App\Models;

use App\Agovena\Money\Money;
use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generic sellable product. Variants deferred to a later catalog iteration.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProductStatus $status
 * @property int $price_amount
 * @property string $currency
 * @property string|null $image_path
 * @property int|null $category_id
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'status',
    'price_amount',
    'currency',
    'image_path',
    'category_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price_amount' => 'integer',
        ];
    }

    public function money(): Money
    {
        return Money::of($this->price_amount, $this->currency);
    }

    public function isPurchasable(): bool
    {
        return $this->status->isPurchasable();
    }

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }
}

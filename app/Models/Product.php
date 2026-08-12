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
 * @property string|null $subtitle
 * @property string $slug
 * @property string|null $description
 * @property array<int, array{label: string, value: string}>|null $specifications
 * @property bool $show_details
 * @property bool $show_specifications
 * @property ProductStatus $status
 * @property int $price_amount
 * @property string $currency
 * @property string|null $image_path
 * @property int|null $category_id
 */
#[Fillable([
    'name',
    'subtitle',
    'slug',
    'description',
    'specifications',
    'show_details',
    'show_specifications',
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
            'specifications' => 'array',
            'show_details' => 'boolean',
            'show_specifications' => 'boolean',
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

    /**
     * Spec groups for storefront Details tab (overview + merchant specifications).
     *
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    public function specificationGroups(): array
    {
        $overview = array_values(array_filter([
            $this->category ? ['label' => 'Category', 'value' => $this->category->name] : null,
            ['label' => 'SKU', 'value' => strtoupper(str_replace('-', ' ', $this->slug))],
            ['label' => 'Currency', 'value' => strtoupper($this->currency)],
            ['label' => 'Availability', 'value' => $this->status->value === 'active' ? 'Available' : ucfirst($this->status->value)],
        ]));

        $groups = [
            [
                'title' => 'Overview',
                'rows' => $overview,
            ],
        ];

        /** @var list<array{label?: mixed, value?: mixed}> $specs */
        $specs = is_array($this->specifications) ? $this->specifications : [];
        $rows = [];
        foreach ($specs as $row) {
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $value = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($label === '' || $value === '') {
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $value];
        }

        if ($rows !== []) {
            $groups[] = [
                'title' => 'Specifications',
                'rows' => $rows,
            ];
        }

        return $groups;
    }
}

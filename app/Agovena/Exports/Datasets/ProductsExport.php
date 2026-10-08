<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catalog products. Capability configuration (which may hold provider
 * settings) is deliberately not exported.
 */
final class ProductsExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'products';
    }

    public function permission(): string
    {
        return 'products.view';
    }

    public function recordElement(): string
    {
        return 'product';
    }

    public function columns(): array
    {
        return [
            'id',
            'sku',
            'name',
            'slug',
            'status',
            'category_id',
            'category_slug',
            'price',
            'currency',
            'subtitle',
            'description',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Product::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Product> $query */
        $query = $filters->apply(Product::query())->with('category:id,slug');

        foreach ($query->lazyById(500, 'products.id', 'id') as $product) {
            /** @var Product $product */
            yield [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $this->values->enum($product->status),
                'category_id' => $product->category_id,
                'category_slug' => $product->category?->slug,
                'price' => $this->values->money($product->price_amount, $product->currency),
                'currency' => $product->currency,
                'subtitle' => $product->subtitle,
                'description' => $product->description,
                'created_at' => $this->values->dateTime($product->created_at),
                'updated_at' => $this->values->dateTime($product->updated_at),
            ];
        }
    }
}

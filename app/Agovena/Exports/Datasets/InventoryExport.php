<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Availability\Models\InventoryStock;
use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stock levels per product.
 */
final class InventoryExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        return 'inventory.view';
    }

    public function recordElement(): string
    {
        return 'stock_item';
    }

    public function columns(): array
    {
        return [
            'id',
            'product_id',
            'product_sku',
            'product_name',
            'availability_mode',
            'quantity',
            'track_stock',
            'allow_oversell',
            'provider_key',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(InventoryStock::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<InventoryStock> $query */
        $query = $filters->apply(InventoryStock::query())->with('product:id,sku,name');

        foreach ($query->lazyById(500, 'inventory_stocks.id', 'id') as $stock) {
            /** @var InventoryStock $stock */
            yield [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_sku' => $stock->product?->sku,
                'product_name' => $stock->product?->name,
                'availability_mode' => $this->values->enum($stock->availability_mode),
                'quantity' => $stock->quantity,
                'track_stock' => $stock->track_stock,
                'allow_oversell' => $stock->allow_oversell,
                'provider_key' => $stock->provider_key,
                'created_at' => $this->values->dateTime($stock->getAttribute('created_at')),
                'updated_at' => $this->values->dateTime($stock->getAttribute('updated_at')),
            ];
        }
    }
}

<?php

declare(strict_types=1);

use App\Agovena\Availability\InventoryService;
use App\Agovena\Availability\Models\InventoryStock;
use App\Agovena\Catalog\Contracts\ProductStock;
use App\Models\Product;

test('changing a quantity through the catalog stock contract keeps the stock policy', function () {
    $product = Product::factory()->create();
    app(InventoryService::class)->setQuantity($product, 5, trackStock: false, allowOversell: true);

    app(ProductStock::class)->setQuantity($product, 9);

    $stock = InventoryStock::query()->where('product_id', $product->id)->firstOrFail();

    expect($stock->quantity)->toBe(9)
        ->and($stock->track_stock)->toBeFalse()
        ->and($stock->allow_oversell)->toBeTrue();
});

test('a product without a stock row gets the default stock policy', function () {
    $product = Product::factory()->create();

    app(ProductStock::class)->setQuantity($product, 3);

    $stock = InventoryStock::query()->where('product_id', $product->id)->firstOrFail();

    expect($stock->quantity)->toBe(3)
        ->and($stock->track_stock)->toBeTrue()
        ->and($stock->allow_oversell)->toBeFalse();
});

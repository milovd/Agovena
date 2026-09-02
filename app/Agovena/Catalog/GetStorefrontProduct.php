<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GetStorefrontProduct
{
    public function __construct(
        private readonly ProductCapabilityRegistry $capabilities,
    ) {}

    public function handle(string $slug): Product
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['category.parent', 'images', 'currencyPrices'])
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        if (! $this->capabilities->productIsAvailable($product)) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        return $product;
    }
}

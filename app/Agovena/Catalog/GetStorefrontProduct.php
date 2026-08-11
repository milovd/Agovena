<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GetStorefrontProduct
{
    public function handle(string $slug): Product
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['category.parent', 'images'])
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        return $product;
    }
}

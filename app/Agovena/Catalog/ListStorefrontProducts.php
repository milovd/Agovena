<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class ListStorefrontProducts
{
    /** @return Collection<int, Product> */
    public function handle(?int $categoryId = null): Collection
    {
        $query = Product::query()
            ->active()
            ->with('category')
            ->orderBy('name');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query->get();
    }
}

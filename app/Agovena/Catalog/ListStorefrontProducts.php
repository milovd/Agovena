<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class ListStorefrontProducts
{
    /**
     * @param  list<int>|null  $categoryIds
     * @return Collection<int, Product>
     */
    public function handle(?int $categoryId = null, ?array $categoryIds = null): Collection
    {
        $query = Product::query()
            ->active()
            ->with('category')
            ->orderBy('name');

        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        } elseif ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query->get();
    }
}

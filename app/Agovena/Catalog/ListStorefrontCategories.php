<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class ListStorefrontCategories
{
    /** @return Collection<int, Category> */
    public function handle(bool $onlyWithProducts = false): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get();

        if ($onlyWithProducts) {
            return $categories->where('products_count', '>', 0)->values();
        }

        return $categories;
    }
}

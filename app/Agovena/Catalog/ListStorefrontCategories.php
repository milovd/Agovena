<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class ListStorefrontCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(bool $onlyWithProducts = false, bool $rootsOnly = true): Collection
    {
        $query = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name');

        if ($rootsOnly) {
            $query
                ->whereNull('parent_id')
                ->with([
                    'children' => fn ($q) => $q
                        ->where('is_active', true)
                        ->withCount(['products' => fn ($child) => $child->active()])
                        ->orderBy('name'),
                ]);
        }

        $categories = $query->get();

        if ($onlyWithProducts) {
            return $categories
                ->filter(function (Category $category): bool {
                    $own = (int) ($category->products_count ?? 0);
                    $child = $category->relationLoaded('children')
                        ? (int) $category->children->sum('products_count')
                        : 0;

                    return ($own + $child) > 0;
                })
                ->values();
        }

        return $categories;
    }
}

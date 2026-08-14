<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class SuggestStorefrontProducts
{
    /** @return Collection<int, Product> */
    public function handle(string $query, int $limit = 3): Collection
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $limit = max(1, min($limit, 8));

        return Product::query()
            ->active()
            ->with(['category', 'images'])
            ->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}

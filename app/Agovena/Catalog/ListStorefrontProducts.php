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
    public function handle(
        ?int $categoryId = null,
        ?array $categoryIds = null,
        ?string $search = null,
        ?string $sort = null,
        ?int $limit = null,
    ): Collection {
        $query = Product::query()
            ->active()
            ->with('category');

        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        } elseif ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price_amount')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price_amount')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }
}

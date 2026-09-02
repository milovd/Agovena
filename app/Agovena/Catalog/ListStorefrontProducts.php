<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListStorefrontProducts
{
    public function __construct(
        private readonly ProductCapabilityRegistry $capabilities,
    ) {}

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
        ?int $excludeId = null,
        ?string $currency = null,
    ): Collection {
        $query = $this->query($categoryId, $categoryIds, $search, $sort, $excludeId, $currency);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(
        ?int $categoryId = null,
        ?array $categoryIds = null,
        ?string $search = null,
        ?string $sort = null,
        int $perPage = 24,
        ?int $excludeId = null,
        ?string $currency = null,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 48));

        return $this->query($categoryId, $categoryIds, $search, $sort, $excludeId, $currency)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return Builder<Product>
     */
    private function query(
        ?int $categoryId,
        ?array $categoryIds,
        ?string $search,
        ?string $sort,
        ?int $excludeId,
        ?string $currency = null,
    ): Builder {
        $query = Product::query()
            ->active()
            ->with(['category', 'images', 'currencyPrices']);
        $this->capabilities->constrainToAvailable($query);

        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        } elseif ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        if (is_string($currency) && $currency !== '') {
            $query->where('currency', strtoupper($currency));
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

        return $query;
    }
}

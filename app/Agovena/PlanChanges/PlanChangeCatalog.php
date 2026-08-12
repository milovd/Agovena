<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPlanChange;
use Illuminate\Support\Collection;

final class PlanChangeCatalog
{
    /** @return Collection<int, ProductPlanChange> */
    public function targets(Product $from): Collection
    {
        return ProductPlanChange::query()
            ->with('toProduct.capabilities')
            ->where('from_product_id', $from->id)
            ->where('is_active', true)
            ->whereHas('toProduct', static fn ($query) => $query->where('status', ProductStatus::Active))
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}

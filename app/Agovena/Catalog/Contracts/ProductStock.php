<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Contracts;

use App\Models\Product;

/**
 * Core Availability persistence contract. Bound by the Core availability provider.
 */
interface ProductStock
{
    public function quantityFor(Product $product): int;

    public function setQuantity(Product $product, int $quantity): void;
}

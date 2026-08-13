<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Contracts;

use App\Models\Product;

/**
 * Optional inventory persistence. Bound by the Inventory Module when enabled.
 */
interface ProductStock
{
    public function quantityFor(Product $product): int;

    public function setQuantity(Product $product, int $quantity): void;
}

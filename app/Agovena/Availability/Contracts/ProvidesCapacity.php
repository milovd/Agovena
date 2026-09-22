<?php

declare(strict_types=1);

namespace App\Agovena\Availability\Contracts;

use App\Models\Product;

interface ProvidesCapacity
{
    public function key(): string;

    public function canFulfil(Product $product, int $quantity): bool;
}

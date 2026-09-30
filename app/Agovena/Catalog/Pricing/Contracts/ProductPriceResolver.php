<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Pricing\Contracts;

use App\Agovena\Money\Money;
use App\Models\Product;

interface ProductPriceResolver
{
    public function id(): string;

    public function supports(Product $product): bool;

    /**
     * @param  array<string, mixed>  $selections
     */
    public function resolve(Product $product, array $selections, string $currency): Money;
}

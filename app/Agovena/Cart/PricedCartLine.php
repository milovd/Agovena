<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Money\Money;

final readonly class PricedCartLine
{
    public function __construct(
        public int $productId,
        public string $label,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
    ) {}
}

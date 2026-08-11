<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

final readonly class CartLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}

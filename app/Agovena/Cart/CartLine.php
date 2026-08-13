<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Catalog\Options\CartLineKey;

final readonly class CartLine
{
    public string $lineKey;

    /**
     * @param  array<string, mixed>  $selections
     */
    public function __construct(
        public int $productId,
        public int $quantity,
        public array $selections = [],
        ?string $lineKey = null,
    ) {
        $this->lineKey = $lineKey ?? CartLineKey::make($productId, $selections);
    }
}

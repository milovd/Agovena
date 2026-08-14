<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Money\Money;

final readonly class PricedCartLine
{
    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array{key: string, label: string, display: string}>  $optionLabels
     */
    public function __construct(
        public int $productId,
        public string $label,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        public string $lineKey = '',
        public array $selections = [],
        public array $optionLabels = [],
        public ?string $slug = null,
        public ?string $imageUrl = null,
    ) {}
}

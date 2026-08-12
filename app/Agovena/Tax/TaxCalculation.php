<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use App\Agovena\Money\Money;

final readonly class TaxCalculation
{
    public function __construct(
        public Money $tax,
        public ?string $rateName,
        public ?int $rateBps,
    ) {}
}

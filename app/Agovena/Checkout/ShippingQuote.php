<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Money\Money;

/**
 * Generic checkout shipping quote. Modules (e.g. Shipping) provide real quotes;
 * Core never knows carrier providers.
 */
final readonly class ShippingQuote
{
    public function __construct(
        public int $methodId,
        public string $label,
        public Money $amount,
    ) {}
}

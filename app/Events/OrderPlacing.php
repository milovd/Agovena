<?php

declare(strict_types=1);

namespace App\Events;

use App\Agovena\Cart\PricedCartLine;

/**
 * Fired before an order is persisted. Modules may assert purchasability (e.g. stock).
 */
final class OrderPlacing
{
    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function __construct(
        public readonly array $lines,
    ) {}
}

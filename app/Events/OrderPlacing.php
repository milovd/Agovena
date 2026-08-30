<?php

declare(strict_types=1);

namespace App\Events;

use App\Agovena\Cart\PricedCartLine;
use App\Models\Order;

/**
 * Fired while an order is being persisted, before its transaction commits.
 * Modules may assert purchasability (e.g. stock) and roll the order back.
 */
final class OrderPlacing
{
    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly ?Order $order = null,
        public readonly ?OrderPreflight $preflight = null,
    ) {}
}

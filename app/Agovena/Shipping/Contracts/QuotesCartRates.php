<?php

declare(strict_types=1);

namespace App\Agovena\Shipping\Contracts;

use App\Agovena\Cart\PricedCartLine;
use App\Agovena\Checkout\ShippingDestination;
use App\Agovena\Shipping\ShippingRateQuote;

/**
 * Optional carrier capability for checkout-time rates.
 * Failures must return an empty list so merchant-configured methods remain available.
 */
interface QuotesCartRates
{
    /**
     * @param  list<PricedCartLine>  $lines
     * @return list<ShippingRateQuote>
     */
    public function quoteCart(array $lines, ShippingDestination $destination, string $currency): array;
}

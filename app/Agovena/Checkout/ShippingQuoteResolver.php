<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\PricedCartLine;

interface ShippingQuoteResolver
{
    /**
     * @param  list<PricedCartLine>  $lines
     * @return list<ShippingQuote>
     */
    public function quotes(array $lines, string $countryCode, string $currency, ?ShippingDestination $destination = null): array;

    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function quote(array $lines, string $countryCode, string $currency, int $methodId, ?ShippingDestination $destination = null): ?ShippingQuote;

    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function quoteByKey(array $lines, string $countryCode, string $currency, string $key, ?ShippingDestination $destination = null): ?ShippingQuote;
}

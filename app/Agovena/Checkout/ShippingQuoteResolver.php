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
    public function quotes(array $lines, string $countryCode, string $currency): array;

    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function quote(array $lines, string $countryCode, string $currency, int $methodId): ?ShippingQuote;
}

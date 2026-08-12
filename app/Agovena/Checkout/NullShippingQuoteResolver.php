<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

/**
 * Default: no shipping methods until a Module binds a real resolver.
 */
final class NullShippingQuoteResolver implements ShippingQuoteResolver
{
    public function quotes(array $lines, string $countryCode, string $currency): array
    {
        return [];
    }

    public function quote(array $lines, string $countryCode, string $currency, int $methodId): ?ShippingQuote
    {
        return null;
    }
}

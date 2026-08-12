<?php

declare(strict_types=1);

namespace Agovena\Modules\Shipping;

use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Agovena\Checkout\ShippingQuote;
use App\Agovena\Checkout\ShippingQuoteResolver;

final class ModuleShippingQuoteResolver implements ShippingQuoteResolver
{
    public function __construct(
        private readonly ShippingRateCalculator $calculator,
    ) {}

    public function quotes(array $lines, string $countryCode, string $currency): array
    {
        if ($lines === []) {
            return [];
        }

        $methods = ShippingMethod::query()
            ->with('zone')
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $quotes = [];
        foreach ($methods as $method) {
            if (! $this->calculator->isEligible($method, $lines, $countryCode, $currency)) {
                continue;
            }
            $quotes[] = new ShippingQuote(
                methodId: $method->id,
                label: $method->name,
                amount: $this->calculator->amount($method, $lines, $currency),
            );
        }

        return $quotes;
    }

    public function quote(array $lines, string $countryCode, string $currency, int $methodId): ?ShippingQuote
    {
        foreach ($this->quotes($lines, $countryCode, $currency) as $quote) {
            if ($quote->methodId === $methodId) {
                return $quote;
            }
        }

        return null;
    }
}

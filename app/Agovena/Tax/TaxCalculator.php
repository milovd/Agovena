<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use App\Agovena\Money\Money;
use App\Models\TaxRate;

final class TaxCalculator
{
    public function calculate(
        Money $subtotalAfterDiscount,
        Money $shipping,
        string $country,
        bool $pricesIncludeTax,
        ?TaxRate $rate,
    ): TaxCalculation {
        if ($rate === null || ! $rate->is_active || ! $this->matchesCountry($rate, $country)) {
            return new TaxCalculation(Money::of(0, $subtotalAfterDiscount->currency), null, null);
        }

        $basis = $subtotalAfterDiscount;
        if ($rate->applies_to_shipping) {
            $basis = $basis->add($shipping);
        }

        $taxAmount = $pricesIncludeTax
            ? intdiv(($basis->amount * $rate->rate_bps) + intdiv(10000 + $rate->rate_bps, 2), 10000 + $rate->rate_bps)
            : intdiv(($basis->amount * $rate->rate_bps) + 5000, 10000);

        return new TaxCalculation(
            Money::of($taxAmount, $basis->currency),
            $rate->name,
            $rate->rate_bps,
        );
    }

    private function matchesCountry(TaxRate $rate, string $country): bool
    {
        return $rate->country === null || strtoupper($rate->country) === strtoupper($country);
    }
}

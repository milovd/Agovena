<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use App\Agovena\Money\Money;

final class TaxCalculator
{
    public function calculate(
        Money $subtotalAfterDiscount,
        Money $shipping,
        bool $pricesIncludeTax,
        ?ResolvedTaxRate $rate,
    ): TaxCalculation {
        if ($rate === null || ! $rate->applies()) {
            return new TaxCalculation(Money::of(0, $subtotalAfterDiscount->currency), null, null);
        }

        $basis = $subtotalAfterDiscount;
        if ($rate->appliesToShipping) {
            $basis = $basis->add($shipping);
        }

        $taxAmount = $pricesIncludeTax
            ? intdiv(($basis->amount * $rate->rateBps) + intdiv(10000 + $rate->rateBps, 2), 10000 + $rate->rateBps)
            : intdiv(($basis->amount * $rate->rateBps) + 5000, 10000);

        return new TaxCalculation(
            Money::of($taxAmount, $basis->currency),
            $rate->name,
            $rate->rateBps,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\Money;
use App\Models\Currency;

final class MoneyFormatter
{
    public static function format(Money|int $amount, ?string $currency = null): string
    {
        if ($amount instanceof Money) {
            $currency = $amount->currency;
            $amount = $amount->amount;
        }

        $currency ??= 'EUR';

        $defined = app(CurrencyCatalog::class)->find($currency);
        if ($defined !== null) {
            return $defined->formatMinorUnits($amount);
        }

        return self::fallback($amount, $currency);
    }

    private static function fallback(int $amount, string $currency): string
    {
        $fallback = new Currency([
            'code' => $currency,
            'name' => $currency,
            'prefix' => $currency.' ',
            'suffix' => '',
            'precision' => 2,
            'is_active' => true,
        ]);

        return $fallback->formatMinorUnits($amount);
    }
}

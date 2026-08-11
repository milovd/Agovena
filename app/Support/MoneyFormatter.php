<?php

declare(strict_types=1);

namespace App\Support;

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\Money;

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
        return sprintf('%s %s', $currency, number_format($amount / 100, 2, '.', ','));
    }
}

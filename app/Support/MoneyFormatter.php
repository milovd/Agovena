<?php

declare(strict_types=1);

namespace App\Support;

use App\Agovena\Money\Money;
use NumberFormatter;

final class MoneyFormatter
{
    public static function format(Money|int $amount, ?string $currency = null): string
    {
        if ($amount instanceof Money) {
            $currency = $amount->currency;
            $amount = $amount->amount;
        }

        $currency ??= 'EUR';

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY);

            return $formatter->formatCurrency($amount / 100, $currency) ?: self::fallback($amount, $currency);
        }

        return self::fallback($amount, $currency);
    }

    private static function fallback(int $amount, string $currency): string
    {
        return sprintf('%s %.2f', $currency, $amount / 100);
    }
}

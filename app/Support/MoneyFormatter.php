<?php

declare(strict_types=1);

namespace App\Support;

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\Money;
use App\Models\Currency;
use InvalidArgumentException;

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

    /**
     * Human-editable major-unit string for Admin forms (e.g. 45.00).
     */
    public static function majorInputFromMinor(int $minorAmount, string $currency = 'EUR'): string
    {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        $precision = self::precisionFor($currency);
        if ($precision === 0) {
            return (string) $minorAmount;
        }

        $scale = 10 ** $precision;
        $whole = intdiv($minorAmount, $scale);
        $fraction = $minorAmount % $scale;

        return $whole.'.'.str_pad((string) $fraction, $precision, '0', STR_PAD_LEFT);
    }

    /**
     * Parse Admin major-unit input (45, 45.00, 45,00) into integer minor units. No floats.
     */
    public static function minorFromMajorInput(string $input, string $currency = 'EUR'): int
    {
        $normalized = trim(str_replace(' ', '', $input));
        if ($normalized === '') {
            throw new InvalidArgumentException('Price is required.');
        }

        if (! preg_match('/^\d{1,12}([.,]\d{1,6})?$/', $normalized)) {
            throw new InvalidArgumentException('Enter a valid price such as 45 or 45.00.');
        }

        $precision = self::precisionFor($currency);
        $normalized = str_replace(',', '.', $normalized);
        $parts = explode('.', $normalized, 2);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '0';

        if (strlen($fraction) > $precision) {
            throw new InvalidArgumentException(
                $precision === 0
                    ? 'This currency does not allow decimal places.'
                    : "Use at most {$precision} decimal places."
            );
        }

        $fraction = str_pad($fraction, $precision, '0', STR_PAD_RIGHT);
        $scale = 10 ** $precision;

        return ((int) $whole) * $scale + (int) $fraction;
    }

    private static function precisionFor(string $currency): int
    {
        $defined = app(CurrencyCatalog::class)->find($currency);
        if ($defined !== null) {
            return $defined->normalizedPrecision();
        }

        return 2;
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

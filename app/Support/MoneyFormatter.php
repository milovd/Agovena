<?php

declare(strict_types=1);

namespace App\Support;

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\CurrencyConverter;
use App\Agovena\Money\Money;
use App\Agovena\Money\ResolveProductPrice;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Models\Currency;
use App\Models\Product;
use InvalidArgumentException;
use Throwable;

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
     * Format for UI display in the visitor/admin preferred currency (session), converting via rates.
     * Does not change stored amounts - invoices/checkout settlement stay in their native currency.
     */
    public static function formatDisplay(Money|int $amount, ?string $currency = null, ?string $displayCurrency = null): string
    {
        if ($amount instanceof Money) {
            $currency = $amount->currency;
            $amount = $amount->amount;
        }

        $currency ??= 'EUR';
        $displayCurrency ??= self::preferredDisplayCurrency();

        if (strtoupper($currency) === strtoupper($displayCurrency)) {
            return self::format($amount, $currency);
        }

        try {
            $converted = app(CurrencyConverter::class)->convert($amount, $currency, $displayCurrency);

            return self::format($converted, $displayCurrency);
        } catch (Throwable) {
            return self::format($amount, $currency);
        }
    }

    /**
     * Format a product price for the preferred (or given) currency.
     * Returns null when the product is not available in that currency.
     */
    public static function formatProduct(Product $product, ?string $displayCurrency = null): ?string
    {
        $resolved = app(ResolveProductPrice::class)->resolve($product, $displayCurrency);
        if ($resolved === null) {
            return null;
        }

        return self::format($resolved->money);
    }

    public static function preferredDisplayCurrency(): string
    {
        try {
            return app(StorefrontPreferences::class)->currencyCode();
        } catch (Throwable) {
            return 'EUR';
        }
    }

    /**
     * Human-editable major-unit string for Admin forms (e.g. 45.00).
     */
    public static function majorInputFromMinor(int $minorAmount, string $currency = 'EUR'): string
    {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException(__('admin.products.validation.amount_negative'));
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
            throw new InvalidArgumentException(__('admin.products.validation.price_required'));
        }

        if (! preg_match('/^\d{1,12}([.,]\d{1,6})?$/', $normalized)) {
            throw new InvalidArgumentException(__('admin.products.validation.price_invalid'));
        }

        $precision = self::precisionFor($currency);
        $normalized = str_replace(',', '.', $normalized);
        $parts = explode('.', $normalized, 2);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '0';

        if (strlen($fraction) > $precision) {
            throw new InvalidArgumentException(
                $precision === 0
                    ? __('admin.products.validation.price_no_decimals')
                    : trans_choice('admin.products.validation.price_decimals', $precision, ['count' => $precision])
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
            'exchange_rate' => '1.00000000',
            'is_active' => true,
        ]);

        return $fallback->formatMinorUnits($amount);
    }
}

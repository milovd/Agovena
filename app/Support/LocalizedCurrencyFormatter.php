<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;
use RuntimeException;

/** Locale-aware currency display without converting integer minor units to floats. */
final class LocalizedCurrencyFormatter
{
    private NumberFormatter $integerFormatter;

    private string $decimalSeparator;

    private string $zeroDigits;

    private string $currencyTemplate;

    private int $scale;

    public function __construct(string $locale, private readonly string $currency, private readonly int $precision)
    {
        if ($precision < 0 || $precision > 6) {
            throw new InvalidArgumentException('Unsupported currency precision.');
        }

        $this->scale = 10 ** $precision;
        $this->integerFormatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $this->decimalSeparator = $this->integerFormatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $this->zeroDigits = '0'.($precision > 0 ? $this->decimalSeparator.str_repeat('0', $precision) : '');

        $currencyFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $currencyFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
        $currencyFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);
        $template = $currencyFormatter->formatCurrency(0, $currency);
        $this->currencyTemplate = $template !== false && str_contains($template, $this->zeroDigits)
            ? $template
            : $currency.' '.$this->zeroDigits;
    }

    public function format(int $minor): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        $whole = $this->integerFormatter->format(intdiv($minor, $this->scale), NumberFormatter::TYPE_INT64);
        if ($whole === false) {
            throw new RuntimeException('Unable to format currency amount.');
        }

        $digits = $whole.($this->precision > 0
            ? $this->decimalSeparator.str_pad((string) ($minor % $this->scale), $this->precision, '0', STR_PAD_LEFT)
            : '');

        return str_replace($this->zeroDigits, $digits, $this->currencyTemplate);
    }
}

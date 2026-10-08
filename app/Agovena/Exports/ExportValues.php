<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

use App\Agovena\Money\CurrencyCatalog;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * Formats stored values into the export conventions: money as a decimal string
 * using the currency precision, timestamps as ISO 8601 UTC, dates as Y-m-d.
 */
final class ExportValues
{
    /** @var array<string, int> */
    private array $precision = [];

    public function __construct(private readonly CurrencyCatalog $currencies) {}

    public function money(?int $minorAmount, ?string $currency): ?string
    {
        if ($minorAmount === null) {
            return null;
        }

        $precision = $this->precisionFor((string) $currency);
        $sign = $minorAmount < 0 ? '-' : '';
        $absolute = abs($minorAmount);

        if ($precision === 0) {
            return $sign.$absolute;
        }

        $scale = 10 ** $precision;

        return $sign.intdiv($absolute, $scale).'.'.str_pad((string) ($absolute % $scale), $precision, '0', STR_PAD_LEFT);
    }

    public function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface
            ? $value->copy()->utc()->format('Y-m-d\TH:i:s\Z')
            : null;
    }

    public function date(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : null;
    }

    public function enum(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function precisionFor(string $currency): int
    {
        $code = strtoupper($currency);

        return $this->precision[$code] ??= $this->currencies->find($code)?->normalizedPrecision() ?? 2;
    }
}

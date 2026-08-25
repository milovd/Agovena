<?php

declare(strict_types=1);

namespace App\Agovena\Money;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

final class CurrencyCatalog
{
    private const CACHE_PREFIX = 'agovena.currency.';

    public function find(string $code): ?Currency
    {
        $code = strtoupper($code);

        /** @var array{code: string, name: string, prefix: string, suffix: string, precision: int, exchange_rate: string, is_active: bool}|null $data */
        $data = Cache::remember(self::CACHE_PREFIX.$code, 3600, function () use ($code): ?array {
            $currency = Currency::query()->where('code', $code)->first();
            if ($currency === null) {
                return null;
            }

            return [
                'code' => $currency->code,
                'name' => $currency->name,
                'prefix' => $currency->prefix,
                'suffix' => $currency->suffix,
                'precision' => $currency->normalizedPrecision(),
                'exchange_rate' => (string) ($currency->exchange_rate ?? '1.00000000'),
                'is_active' => $currency->is_active,
            ];
        });

        if ($data === null) {
            return null;
        }

        // Rebuild from scalar cache payload - never cache Eloquent instances.
        $currency = new Currency;
        $currency->forceFill($data);
        $currency->syncOriginal();

        return $currency;
    }

    public function forget(string $code): void
    {
        Cache::forget(self::CACHE_PREFIX.strtoupper($code));
    }
}

<?php

declare(strict_types=1);

namespace App\Agovena\Money;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls mid-market rates relative to the shop base currency (Frankfurter / ECB).
 * Rates are written to currencies.exchange_rate for merchant review - never silent checkout FX.
 */
final class SyncCurrencyExchangeRates
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CurrencyCatalog $catalog,
    ) {}

    /**
     * @return array{updated: int, base: string}
     */
    public function handle(): array
    {
        $base = strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR'));
        if ($base === '') {
            $base = 'EUR';
        }

        $codes = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->map(fn (mixed $code): string => strtoupper((string) $code))
            ->reject(fn (string $code): bool => $code === $base)
            ->values()
            ->all();

        Currency::query()->where('code', $base)->update(['exchange_rate' => '1.00000000']);
        $this->catalog->forget($base);

        if ($codes === []) {
            return ['updated' => 1, 'base' => $base];
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->get('https://api.frankfurter.app/latest', [
                'from' => $base,
                'to' => implode(',', $codes),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Could not fetch exchange rates.');
        }

        /** @var array<string, float|int|string> $rates */
        $rates = $response->json('rates') ?? [];
        $updated = 1;

        foreach ($codes as $code) {
            if (! array_key_exists($code, $rates)) {
                continue;
            }

            $rate = $this->toRateString($rates[$code]);
            Currency::query()->where('code', $code)->update(['exchange_rate' => $rate]);
            $this->catalog->forget($code);
            $updated++;
        }

        return ['updated' => $updated, 'base' => $base];
    }

    private function toRateString(float|int|string $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || ! is_numeric($value)) {
                throw new RuntimeException('Invalid exchange rate payload.');
            }

            return bcadd($value, '0', 8);
        }

        // API returns JSON numbers; stringify without float math on the rate itself.
        return bcadd(sprintf('%.8F', $value), '0', 8);
    }
}

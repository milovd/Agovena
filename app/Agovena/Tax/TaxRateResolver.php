<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use App\Agovena\Settings\SettingsRepository;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

final class TaxRateResolver
{
    public function __construct(
        private readonly AutomaticTaxRateProvider $automaticRates,
        private readonly SettingsRepository $settings,
    ) {}

    public function taxEnabled(): bool
    {
        return $this->boolSetting('tax_enabled', true);
    }

    public function automaticTaxRates(): bool
    {
        return $this->boolSetting('automatic_tax_rates', true);
    }

    /**
     * Resolution order when tax is enabled:
     * - Automatic ON: country override (or disable) → remote automatic rate → no tax
     * - Automatic OFF: country TaxRate → null-country fallback → no tax
     * When tax master is OFF: always no tax
     */
    public function resolve(string $country): ResolvedTaxRate
    {
        if (! $this->taxEnabled()) {
            return ResolvedTaxRate::none();
        }

        $country = strtoupper(trim($country));
        if ($country === '') {
            return ResolvedTaxRate::none();
        }

        $specific = $this->countryRate($country);
        if ($specific !== null) {
            if ($specific->is_disabled) {
                return ResolvedTaxRate::fromDisabled($specific);
            }

            return ResolvedTaxRate::fromOverride($specific);
        }

        if ($this->automaticTaxRates()) {
            $rateBps = $this->automaticRates->standardRateBps($country);
            if ($rateBps !== null) {
                return ResolvedTaxRate::fromAutomatic(
                    $country,
                    $this->automaticRates->rateName($country),
                    $rateBps,
                );
            }

            return ResolvedTaxRate::none();
        }

        $fallback = TaxRate::query()
            ->where('is_active', true)
            ->whereNull('country')
            ->where('is_disabled', false)
            ->orderBy('id')
            ->first();

        if ($fallback !== null) {
            return ResolvedTaxRate::fromFallback($fallback);
        }

        return ResolvedTaxRate::none();
    }

    /**
     * Merchant-created TaxRate rows only (never automatic defaults).
     *
     * @return Collection<int, TaxRate>
     */
    public function merchantRates(): Collection
    {
        return TaxRate::query()
            ->orderByRaw('country is null')
            ->orderBy('country')
            ->orderBy('name')
            ->get();
    }

    private function countryRate(string $country): ?TaxRate
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->where('country', $country)
            ->orderBy('id')
            ->first();
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = $this->settings->get('store', $key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return $value === 1 || $value === '1' || $value === 'true';
    }
}

<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

/**
 * Static EU VAT standard rates for automated tests and offline local use only.
 *
 * NOT the production automatic tax path. Bind via
 * AGOVENA_TAX_AUTOMATIC_PROVIDER=catalog. Production uses VatnodeRemoteTaxRateProvider.
 *
 * Snapshot aligned with vatnode / EC TEDB data as of 2026-08-24 (standard rates only).
 */
final class StaticCatalogTaxRateProvider implements AutomaticTaxRateProvider
{
    public const VERSION = 'test-catalog-2026-08-24';

    /**
     * ISO 3166-1 alpha-2 => standard rate in basis points (2100 = 21.00%).
     *
     * @var array<string, int>
     */
    private const STANDARD_BPS = [
        'AT' => 2000,
        'BE' => 2100,
        'BG' => 2000,
        'CY' => 1900,
        'CZ' => 2100,
        'DE' => 1900,
        'DK' => 2500,
        'EE' => 2400,
        'ES' => 2100,
        'FI' => 2550,
        'FR' => 2000,
        'GR' => 2400,
        'HR' => 2500,
        'HU' => 2700,
        'IE' => 2300,
        'IT' => 2200,
        'LT' => 2100,
        'LU' => 1700,
        'LV' => 2100,
        'MT' => 1800,
        'NL' => 2100,
        'PL' => 2300,
        'PT' => 2300,
        'RO' => 2100,
        'SE' => 2500,
        'SI' => 2200,
        'SK' => 2300,
        'CH' => 810,
        'GB' => 2000,
        'IS' => 2400,
        'LI' => 810,
        'NO' => 2500,
        'XI' => 2000,
    ];

    public function standardRateBps(string $country): ?int
    {
        $country = strtoupper(trim($country));

        return self::STANDARD_BPS[$country] ?? null;
    }

    public function rateName(string $country): string
    {
        $country = strtoupper(trim($country));

        return $country.' VAT';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function sourceLabel(): string
    {
        return 'static test catalog (not production)';
    }
}

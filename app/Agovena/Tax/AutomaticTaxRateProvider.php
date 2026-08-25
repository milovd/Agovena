<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

/**
 * Resolves standard VAT rates for automatic tax when no merchant override exists.
 * Production binds a remote HTTP source; the static catalog is testing/dev only.
 */
interface AutomaticTaxRateProvider
{
    /**
     * Standard rate in basis points (2100 = 21.00%), or null when unknown.
     */
    public function standardRateBps(string $country): ?int;

    public function rateName(string $country): string;

    public function version(): string;

    /**
     * Short human label for Admin help copy (e.g. "vatnode / EC TEDB").
     */
    public function sourceLabel(): string;
}

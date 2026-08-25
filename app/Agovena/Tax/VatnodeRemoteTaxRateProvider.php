<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches European VAT standard rates from the open vatnode dataset
 * (EC TEDB-sourced JSON published on jsDelivr / GitHub).
 *
 * @see https://github.com/vatnode/eu-vat-rates-data
 */
final class VatnodeRemoteTaxRateProvider implements AutomaticTaxRateProvider
{
    private const CACHE_KEY = 'agovena.tax.vatnode.rates';

    public function standardRateBps(string $country): ?int
    {
        $country = strtoupper(trim($country));
        if ($country === '') {
            return null;
        }

        $rates = $this->rates();
        if (! isset($rates[$country])) {
            return null;
        }

        return $rates[$country];
    }

    public function rateName(string $country): string
    {
        $country = strtoupper(trim($country));

        return $country.' VAT';
    }

    public function version(): string
    {
        $payload = $this->payload();

        return is_string($payload['version'] ?? null) && $payload['version'] !== ''
            ? $payload['version']
            : 'unknown';
    }

    public function sourceLabel(): string
    {
        return 'vatnode (EC TEDB)';
    }

    /**
     * @return array<string, int>
     */
    private function rates(): array
    {
        $payload = $this->payload();
        /** @var array<string, int> $rates */
        $rates = $payload['standard_bps'] ?? [];

        return $rates;
    }

    /**
     * @return array{version?: string, standard_bps?: array<string, int>}
     */
    private function payload(): array
    {
        $ttl = (int) config('agovena.tax.cache_ttl', 86400);

        /** @var array{version?: string, standard_bps?: array<string, int>} $cached */
        $cached = Cache::remember(self::CACHE_KEY, max(60, $ttl), function (): array {
            return $this->fetchPayload();
        });

        return $cached;
    }

    /**
     * @return array{version: string, standard_bps: array<string, int>}
     */
    private function fetchPayload(): array
    {
        $url = (string) config(
            'agovena.tax.vatnode_url',
            'https://cdn.jsdelivr.net/gh/vatnode/eu-vat-rates-data@main/data/eu-vat-rates-data.json',
        );

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Automatic tax rate fetch failed.', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return ['version' => 'unavailable', 'standard_bps' => []];
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];
            $version = is_string($json['version'] ?? null) ? $json['version'] : 'unknown';
            $rawRates = is_array($json['rates'] ?? null) ? $json['rates'] : [];
            $standardBps = [];

            foreach ($rawRates as $code => $row) {
                if (! is_string($code) || ! is_array($row)) {
                    continue;
                }

                $standard = $row['standard'] ?? null;
                if (! is_numeric($standard)) {
                    continue;
                }

                $bps = (int) round(((float) $standard) * 100);
                if ($bps < 0) {
                    continue;
                }

                $standardBps[strtoupper($code)] = $bps;
            }

            return [
                'version' => $version,
                'standard_bps' => $standardBps,
            ];
        } catch (Throwable $e) {
            Log::warning('Automatic tax rate fetch threw.', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return ['version' => 'unavailable', 'standard_bps' => []];
        }
    }
}

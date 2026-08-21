<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\AddressAutocomplete;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Webshop-style address suggestions: saved customer addresses + Google Places Autocomplete (New).
 * API key stays server-side; Livewire proxies search and place resolution.
 */
final class AddressAutocomplete
{
    private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return list<AddressSuggestion>
     */
    public function suggest(string $query, ?string $countryCode, string $sessionToken, ?Customer $customer = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }

        $suggestions = [];
        if ($customer !== null) {
            $suggestions = [...$suggestions, ...$this->savedSuggestions($customer, $query)];
        }

        if ($this->enabled()) {
            $suggestions = [...$suggestions, ...$this->googleSuggestions($query, $countryCode, $sessionToken)];
        }

        return array_slice($suggestions, 0, 8);
    }

    public function resolve(string $placeId, string $sessionToken): ?ResolvedAddress
    {
        $key = $this->apiKey();
        if ($key === null || $placeId === '' || str_starts_with($placeId, 'saved:')) {
            return null;
        }

        $resource = str_starts_with($placeId, 'places/') ? $placeId : 'places/'.$placeId;

        try {
            $response = $this->client($key)
                ->withHeaders([
                    'X-Goog-FieldMask' => 'id,formattedAddress,addressComponents',
                ])
                ->get('https://places.googleapis.com/v1/'.$resource, [
                    'sessionToken' => $sessionToken,
                    'languageCode' => app()->getLocale(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Google Places details request failed.', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Google Places details returned an error.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        return $this->mapComponents($response->json('addressComponents', []) ?? []);
    }

    public function newSessionToken(): string
    {
        return (string) Str::uuid();
    }

    private function apiKey(): ?string
    {
        $fromEnv = config('services.google_places.key');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        $fromSettings = $this->settings->get('store', 'google_places_api_key', '');
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        return null;
    }

    /**
     * @return list<AddressSuggestion>
     */
    private function savedSuggestions(Customer $customer, string $query): array
    {
        $needle = mb_strtolower($query);

        return $customer->addresses()
            ->orderByDesc('is_default_billing')
            ->orderBy('id')
            ->get()
            ->filter(function (CustomerAddress $address) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $address->label,
                    $address->name,
                    $address->line1,
                    $address->postal_code,
                    $address->city,
                    $address->country,
                ])));

                return str_contains($haystack, $needle);
            })
            ->take(3)
            ->map(function (CustomerAddress $address): AddressSuggestion {
                $label = $address->line1;
                $secondary = trim(implode(', ', array_filter([
                    $address->label ?: $address->name,
                    trim(($address->postal_code ?? '').' '.($address->city ?? '')),
                    $address->country,
                ])));

                return new AddressSuggestion(
                    id: 'saved:'.$address->id,
                    label: $label,
                    secondary: $secondary !== '' ? $secondary : null,
                    source: 'saved',
                    savedAddressId: (int) $address->id,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<AddressSuggestion>
     */
    private function googleSuggestions(string $query, ?string $countryCode, string $sessionToken): array
    {
        $key = $this->apiKey();
        if ($key === null) {
            return [];
        }

        $payload = [
            'input' => $query,
            'languageCode' => app()->getLocale(),
            'sessionToken' => $sessionToken,
            'includedPrimaryTypes' => ['street_address', 'route', 'premise', 'subpremise'],
        ];

        if (is_string($countryCode) && strlen($countryCode) === 2) {
            $payload['regionCode'] = strtoupper($countryCode);
            $payload['includedRegionCodes'] = [strtolower($countryCode)];
        }

        try {
            $response = $this->client($key)->post(self::AUTOCOMPLETE_URL, $payload);
        } catch (\Throwable $e) {
            Log::warning('Google Places autocomplete request failed.', ['message' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Google Places autocomplete returned an error.', [
                'status' => $response->status(),
            ]);

            return [];
        }

        $out = [];
        foreach ($response->json('suggestions', []) ?? [] as $row) {
            $prediction = $row['placePrediction'] ?? null;
            if (! is_array($prediction)) {
                continue;
            }

            $placeId = (string) ($prediction['placeId'] ?? $prediction['place'] ?? '');
            $placeId = str_replace('places/', '', $placeId);
            if ($placeId === '') {
                continue;
            }

            $main = (string) ($prediction['structuredFormat']['mainText']['text']
                ?? $prediction['text']['text']
                ?? '');
            if ($main === '') {
                continue;
            }

            $secondary = $prediction['structuredFormat']['secondaryText']['text'] ?? null;

            $out[] = new AddressSuggestion(
                id: $placeId,
                label: $main,
                secondary: is_string($secondary) && $secondary !== '' ? $secondary : null,
                source: 'google',
            );
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function mapComponents(array $components): ?ResolvedAddress
    {
        $get = function (string $type, bool $short = false) use ($components): ?string {
            foreach ($components as $component) {
                $types = $component['types'] ?? [];
                if (! is_array($types) || ! in_array($type, $types, true)) {
                    continue;
                }
                $value = $short
                    ? ($component['shortText'] ?? $component['longText'] ?? null)
                    : ($component['longText'] ?? $component['shortText'] ?? null);

                return is_string($value) && $value !== '' ? $value : null;
            }

            return null;
        };

        $country = strtoupper((string) ($get('country', short: true) ?? ''));

        $streetNumber = $get('street_number');
        $route = $get('route');
        $line1 = $this->formatStreetLine($route, $streetNumber, $country);
        if ($line1 === '') {
            $line1 = $get('premise') ?? $get('subpremise') ?? '';
        }

        $city = $get('locality')
            ?? $get('postal_town')
            ?? $get('sublocality')
            ?? $get('administrative_area_level_2')
            ?? '';
        $region = $get('administrative_area_level_1');
        $postal = $get('postal_code') ?? '';

        if ($line1 === '' || $city === '' || $postal === '' || strlen($country) !== 2) {
            return null;
        }

        return new ResolvedAddress(
            line1: $line1,
            line2: $get('subpremise'),
            city: $city,
            region: $region,
            postalCode: $postal,
            country: $country,
        );
    }

    private function formatStreetLine(?string $route, ?string $streetNumber, string $country): string
    {
        $route = $route !== null ? trim($route) : '';
        $streetNumber = $streetNumber !== null ? trim($streetNumber) : '';
        if ($route === '' && $streetNumber === '') {
            return '';
        }

        // Most of Europe puts the house number after the street name.
        $numberAfterStreet = in_array($country, [
            'NL', 'BE', 'DE', 'FR', 'AT', 'CH', 'IT', 'ES', 'PT', 'PL', 'CZ', 'SK', 'HU', 'RO', 'DK', 'SE', 'NO', 'FI', 'IE',
        ], true);

        if ($numberAfterStreet) {
            return trim($route.' '.$streetNumber);
        }

        return trim($streetNumber.' '.$route);
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::timeout(8)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $apiKey,
            ]);
    }
}

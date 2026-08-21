<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Agovena\Checkout\AddressAutocomplete\AddressAutocomplete;
use App\Agovena\Checkout\AddressAutocomplete\AddressSuggestion;
use App\Agovena\Checkout\AddressAutocomplete\ResolvedAddress;
use App\Models\Customer;

trait SuggestsAddresses
{
    /** @var list<array{id: string, label: string, secondary: ?string, source: string, saved_address_id: ?int}> */
    public array $addressSuggestions = [];

    public string $addressSuggestScope = '';

    public string $placesSessionToken = '';

    protected function ensurePlacesSession(AddressAutocomplete $autocomplete): void
    {
        if ($this->placesSessionToken === '') {
            $this->placesSessionToken = $autocomplete->newSessionToken();
        }
    }

    protected function refreshAddressSuggestions(string $scope, string $query, ?string $countryCode, AddressAutocomplete $autocomplete, ?Customer $customer = null): void
    {
        $this->ensurePlacesSession($autocomplete);
        $this->addressSuggestScope = $scope;

        $suggestions = $autocomplete->suggest($query, $countryCode, $this->placesSessionToken, $customer);
        $this->addressSuggestions = array_map(
            static fn (AddressSuggestion $suggestion): array => $suggestion->toArray(),
            $suggestions,
        );
    }

    public function clearAddressSuggestions(): void
    {
        $this->addressSuggestions = [];
        $this->addressSuggestScope = '';
    }

    protected function resolveSuggestion(int $index, AddressAutocomplete $autocomplete): AddressSuggestion|ResolvedAddress|null
    {
        $row = $this->addressSuggestions[$index] ?? null;
        if (! is_array($row)) {
            return null;
        }

        $suggestion = AddressSuggestion::fromArray($row);
        if ($suggestion->source === 'saved') {
            return $suggestion;
        }

        $this->ensurePlacesSession($autocomplete);
        $resolved = $autocomplete->resolve($suggestion->id, $this->placesSessionToken);
        $this->placesSessionToken = $autocomplete->newSessionToken();
        $this->clearAddressSuggestions();

        return $resolved;
    }
}

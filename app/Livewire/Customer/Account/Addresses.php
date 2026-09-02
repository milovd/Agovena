<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Checkout\AddressAutocomplete\AddressAutocomplete;
use App\Agovena\Checkout\AddressAutocomplete\AddressSuggestion;
use App\Agovena\Checkout\AddressAutocomplete\ResolvedAddress;
use App\Agovena\Checkout\CheckoutCountries;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\DeleteCustomerAddress;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Concerns\SuggestsAddresses;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Addresses extends Component
{
    use SuggestsAddresses;

    public ?int $editingId = null;

    public string $label = '';

    public string $name = '';

    public string $company = '';

    public string $line1 = '';

    public string $line2 = '';

    public string $city = '';

    public string $region = '';

    public string $postal_code = '';

    public string $country = 'NL';

    public string $phone = '';

    public bool $is_default_billing = false;

    public bool $is_default_shipping = false;

    public bool $embedded = false;

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    public function mount(CustomerPropertyService $properties): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $this->propertyValues = $properties->emptyValues($properties->addressDefinitionsFor('account'), $customer);
    }

    public function edit(int $addressId, CustomerPropertyService $properties): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $address = $customer->addresses()->whereKey($addressId)->firstOrFail();

        $this->editingId = $address->id;
        $this->label = (string) ($address->label ?? '');
        $this->name = $address->name;
        $this->company = (string) ($address->company ?? '');
        $this->line1 = $address->line1;
        $this->line2 = (string) ($address->line2 ?? '');
        $this->city = $address->city;
        $this->region = (string) ($address->region ?? '');
        $this->postal_code = $address->postal_code;
        $this->country = $address->country;
        $this->phone = (string) ($address->phone ?? '');
        $this->is_default_billing = $address->is_default_billing;
        $this->is_default_shipping = $address->is_default_shipping;
        $this->propertyValues = $properties->addressPropertyValues(AddressData::fromArray([
            'name' => $address->name,
            'company' => $address->company,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'phone' => $address->phone,
        ]));
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->label = '';
        $this->name = '';
        $this->company = '';
        $this->line1 = '';
        $this->line2 = '';
        $this->city = '';
        $this->region = '';
        $this->postal_code = '';
        $this->country = 'NL';
        $this->phone = '';
        $this->is_default_billing = false;
        $this->is_default_shipping = false;
        $this->propertyValues = [];
        $this->resetValidation();
    }

    public function updatedLine1(string $value): void
    {
        $this->refreshAddressSuggestions(
            'account',
            $value,
            $this->country !== '' ? $this->country : null,
            app(AddressAutocomplete::class),
            authenticated_customer(),
        );
    }

    public function updatedPropertyValuesAddress(string $value): void
    {
        $this->refreshAddressSuggestions(
            'account',
            $value,
            (string) ($this->propertyValues['country'] ?? $this->country) ?: null,
            app(AddressAutocomplete::class),
            authenticated_customer(),
        );
    }

    public function applyAddressSuggestion(int $index): void
    {
        $result = $this->resolveSuggestion($index, app(AddressAutocomplete::class));
        if ($result instanceof AddressSuggestion && $result->savedAddressId !== null) {
            $this->edit($result->savedAddressId, app(CustomerPropertyService::class));
            $this->clearAddressSuggestions();

            return;
        }

        if (! $result instanceof ResolvedAddress) {
            return;
        }

        $this->propertyValues['address'] = $result->line1;
        $this->line1 = $result->line1;
        if ($result->line2 !== null && $result->line2 !== '') {
            $this->propertyValues['address2'] = $result->line2;
            $this->line2 = $result->line2;
        }
        $this->propertyValues['city'] = $result->city;
        $this->city = $result->city;
        $this->propertyValues['state'] = (string) ($result->region ?? '');
        $this->region = (string) ($result->region ?? '');
        $this->propertyValues['zip'] = $result->postalCode;
        $this->postal_code = $result->postalCode;
        $this->propertyValues['country'] = $result->country;
        $this->country = $result->country;
    }

    public function save(SaveCustomerAddress $save, CustomerPropertyService $properties): void
    {
        $usingLegacyFields = trim((string) ($this->propertyValues['address'] ?? '')) === ''
            && trim($this->line1) !== '';
        $this->syncLegacyAddressFields();
        $propertyDefinitions = $properties->addressDefinitionsFor('account');
        $rules = [
            'label' => ['nullable', 'string', 'max:100'],
            'is_default_billing' => ['boolean'],
            'is_default_shipping' => ['boolean'],
        ];
        if ($usingLegacyFields) {
            $rules = [
                ...$rules,
                'name' => ['required', 'string', 'max:255'],
                'company' => ['nullable', 'string', 'max:255'],
                'line1' => ['required', 'string', 'max:255'],
                'line2' => ['nullable', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:255'],
                'region' => ['nullable', 'string', 'max:255'],
                'postal_code' => ['required', 'string', 'max:32'],
                'country' => ['required', 'string', 'size:2', Rule::in(CheckoutCountries::codes())],
                'phone' => ['nullable', 'string', 'max:64'],
            ];
        } else {
            $rules = [...$rules, ...$properties->livewireRules($propertyDefinitions)];
        }
        $data = $this->validate($rules);

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $existing = $this->editingId !== null
            ? $customer->addresses()->whereKey($this->editingId)->firstOrFail()
            : null;
        $address = $usingLegacyFields
            ? AddressData::fromArray($data)
            : $properties->addressFromProperties($customer, $data['propertyValues'] ?? []);
        if ($address === null) {
            $this->addError('propertyValues.address', __('customer.addresses.required_address'));

            return;
        }
        $syncProperties = (bool) $data['is_default_billing']
            || ($existing === null && ! $customer->addresses()->exists());

        $save->handle(
            $customer,
            $address,
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) $data['is_default_billing'],
                'is_default_shipping' => (bool) $data['is_default_shipping'],
            ],
            $existing,
        );

        if ($syncProperties) {
            $properties->saveAddressProperties($customer, $address, 'customer');
        }

        session()->flash('status', __('customer.addresses.saved'));
        $this->resetForm();
    }

    public function delete(int $addressId, DeleteCustomerAddress $delete): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $address = $customer->addresses()->whereKey($addressId)->firstOrFail();
        $delete->handle($customer, $address);

        if ($this->editingId === $addressId) {
            $this->resetForm();
        }

        session()->flash('status', __('customer.addresses.deleted'));
    }

    public function render(ThemeManager $themes, CustomerPropertyService $properties)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $propertyDefinitions = $properties->addressDefinitionsFor('account');
        if ($this->propertyValues === []) {
            $this->propertyValues = $properties->emptyValues($propertyDefinitions, $customer);
        }

        $data = [
            'theme' => $theme,
            'addresses' => $customer->addresses()->latest('id')->get(),
            'accountSection' => 'profile',
            'countries' => $this->countries(),
            'propertyDefinitions' => $propertyDefinitions,
            'actor' => 'customer',
        ];

        if ($this->embedded) {
            return view($theme->view('account.partials.addresses-panel'), $data);
        }

        return view($theme->view('account.addresses'), $data)->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.profile.heading'),
            'theme' => $theme,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function countries(): array
    {
        return CheckoutCountries::options();
    }

    private function syncLegacyAddressFields(): void
    {
        if (trim((string) ($this->propertyValues['address'] ?? '')) !== '' || trim($this->line1) === '') {
            return;
        }

        $this->propertyValues = [
            ...$this->propertyValues,
            'company_name' => $this->company,
            'address' => $this->line1,
            'address2' => $this->line2,
            'city' => $this->city,
            'state' => $this->region,
            'zip' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
        ];
    }
}

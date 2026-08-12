<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\DeleteCustomerAddress;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Livewire\Component;

final class Addresses extends Component
{
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

    public function edit(int $addressId): void
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
        $this->resetValidation();
    }

    public function save(SaveCustomerAddress $save): void
    {
        $data = $this->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_default_billing' => ['boolean'],
            'is_default_shipping' => ['boolean'],
        ]);

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $existing = $this->editingId !== null
            ? $customer->addresses()->whereKey($this->editingId)->firstOrFail()
            : null;

        $save->handle(
            $customer,
            AddressData::fromArray($data),
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) $data['is_default_billing'],
                'is_default_shipping' => (bool) $data['is_default_shipping'],
            ],
            $existing,
        );

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

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = authenticated_customer();

        return view($theme->view('account.addresses'), [
            'theme' => $theme,
            'addresses' => $customer->addresses()->latest('id')->get(),
            'accountSection' => 'addresses',
            'countries' => $this->countries(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.addresses_title'),
            'theme' => $theme,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function countries(): array
    {
        return [
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'DE' => 'Germany',
            'FR' => 'France',
            'GB' => 'United Kingdom',
            'US' => 'United States',
        ];
    }
}

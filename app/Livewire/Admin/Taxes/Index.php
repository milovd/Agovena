<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Taxes;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\AutomaticTaxRateProvider;
use App\Agovena\Tax\TaxRateResolver;
use App\Models\TaxRate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public bool $tax_enabled = true;

    public bool $automatic_tax_rates = true;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public int $rate_bps = 2100;

    public string $country = '';

    public string $region = '';

    public bool $is_active = true;

    public bool $is_disabled = false;

    public bool $applies_to_shipping = false;

    public string $filter = '';

    public function mount(SettingsRepository $settings): void
    {
        $this->authorize('taxes.view');
        $this->tax_enabled = $this->readBool($settings, 'tax_enabled', true);
        $this->automatic_tax_rates = $this->readBool($settings, 'automatic_tax_rates', true);
    }

    public function updatedTaxEnabled(SettingsRepository $settings): void
    {
        $this->authorize('taxes.manage');
        $settings->set('store', 'tax_enabled', $this->tax_enabled);
        session()->flash('status', __('admin.taxes.settings_saved'));
    }

    public function updatedAutomaticTaxRates(SettingsRepository $settings): void
    {
        $this->authorize('taxes.manage');
        $settings->set('store', 'automatic_tax_rates', $this->automatic_tax_rates);
        session()->flash('status', __('admin.taxes.settings_saved'));
    }

    public function create(): void
    {
        $this->authorize('taxes.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('taxes.manage');
        $rate = TaxRate::query()->findOrFail($id);
        $this->editingId = $rate->id;
        $this->name = $rate->name;
        $this->rate_bps = $rate->rate_bps;
        $this->country = (string) $rate->country;
        $this->region = (string) $rate->region;
        $this->is_active = $rate->is_active;
        $this->is_disabled = $rate->is_disabled;
        $this->applies_to_shipping = $rate->applies_to_shipping;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('taxes.manage');
        $this->country = strtoupper(trim($this->country));
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'rate_bps' => ['required', 'integer', 'min:0', 'max:100000'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_disabled' => ['boolean'],
            'applies_to_shipping' => ['boolean'],
        ];
        if ($this->country !== '') {
            $rules['country'][] = Rule::unique('tax_rates', 'country')->ignore($this->editingId);
        }
        $data = $this->validate($rules);
        $data['country'] = $data['country'] ?: null;
        $data['region'] = $data['region'] ?: null;
        if ($data['is_disabled']) {
            $data['rate_bps'] = 0;
            if ($data['country'] === null) {
                $this->addError('country', __('admin.taxes.disable_requires_country'));

                return;
            }
        }

        TaxRate::query()->updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('status', __($this->editingId ? 'admin.taxes.updated' : 'admin.taxes.created'));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('taxes.manage');
        TaxRate::query()->findOrFail($id)->delete();
        session()->flash('status', __('admin.taxes.deleted'));
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function updatedCountry(AutomaticTaxRateProvider $rates): void
    {
        $country = strtoupper(trim($this->country));
        if ($this->editingId !== null || strlen($country) !== 2 || $this->is_disabled) {
            return;
        }

        $rateBps = $rates->standardRateBps($country);
        if ($rateBps !== null && ($this->name === '' || $this->rate_bps === 2100)) {
            if ($this->name === '') {
                $this->name = $country.' VAT';
            }
            if ($this->rate_bps === 2100) {
                $this->rate_bps = $rateBps;
            }
        }
    }

    public function render(AdminRegistrar $admin, TaxRateResolver $resolver, AutomaticTaxRateProvider $rates)
    {
        $filter = strtoupper(trim($this->filter));
        $merchantRates = $resolver->merchantRates()
            ->when($filter !== '', fn ($rows) => $rows->filter(
                fn (TaxRate $rate): bool => str_contains(strtoupper((string) $rate->country), $filter)
                    || str_contains(strtoupper($rate->name), $filter),
            ))
            ->values();

        return view('livewire.admin.taxes.index', [
            'rates' => $merchantRates,
            'automaticSourceLabel' => $rates->sourceLabel(),
            'automaticVersion' => $rates->version(),
        ])->layout('layouts.admin', [
            'title' => __('admin.taxes.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'country', 'region', 'applies_to_shipping', 'is_disabled']);
        $this->rate_bps = 2100;
        $this->is_active = true;
        $this->resetValidation();
    }

    private function readBool(SettingsRepository $settings, string $key, bool $default): bool
    {
        $value = $settings->get('store', $key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return $value === 1 || $value === '1' || $value === 'true';
    }
}

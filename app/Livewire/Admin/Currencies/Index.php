<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Currencies;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $prefix = '';

    public string $suffix = '';

    public int $precision = 2;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('currencies.view');
    }

    public function create(): void
    {
        $this->authorize('currencies.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $currencyId): void
    {
        $this->authorize('currencies.update');
        $currency = Currency::query()->findOrFail($currencyId);
        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->prefix = $currency->prefix;
        $this->suffix = $currency->suffix;
        $this->precision = $currency->normalizedPrecision();
        $this->is_active = $currency->is_active;
        $this->showForm = true;
    }

    public function save(CurrencyCatalog $catalog): void
    {
        if ($this->editingId === null) {
            $this->authorize('currencies.create');
        } else {
            $this->authorize('currencies.update');
        }

        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                Rule::unique('currencies', 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:16'],
            'suffix' => ['nullable', 'string', 'max:16'],
            'precision' => ['required', 'integer', 'min:0', 'max:6'],
            'is_active' => ['boolean'],
        ]);

        $data['prefix'] = $data['prefix'] ?? '';
        $data['suffix'] = $data['suffix'] ?? '';

        if ($this->editingId === null) {
            Currency::query()->create($data);
            $catalog->forget($data['code']);
            session()->flash('status', __('admin.currencies.flash.created'));
        } else {
            $currency = Currency::query()->findOrFail($this->editingId);
            $previousCode = $currency->code;
            $currency->update($data);
            $catalog->forget($previousCode);
            $catalog->forget($data['code']);
            session()->flash('status', __('admin.currencies.flash.updated'));
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function setAsBase(int $currencyId, SettingsRepository $settings): void
    {
        $this->authorize('settings.update');

        $currency = Currency::query()->whereKey($currencyId)->where('is_active', true)->firstOrFail();
        $settings->set('general', 'base_currency', $currency->code);
        session()->flash('status', __('admin.currencies.flash.base_set', ['code' => $currency->code]));
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin, SettingsRepository $settings)
    {
        return view('livewire.admin.currencies.index', [
            'currencies' => Currency::query()->orderBy('code')->paginate(20),
            'baseCurrency' => (string) $settings->get('general', 'base_currency', 'EUR'),
            'canSetBase' => auth('staff')->user()?->can('settings.update') ?? false,
        ])->layout('layouts.admin', [
            'title' => __('admin.currencies.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->prefix = '';
        $this->suffix = '';
        $this->precision = 2;
        $this->is_active = true;
        $this->resetValidation();
    }
}

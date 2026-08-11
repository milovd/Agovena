<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Currencies;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Money\CurrencyCatalog;
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
            'is_active' => ['boolean'],
        ]);

        $data['prefix'] = $data['prefix'] ?? '';
        $data['suffix'] = $data['suffix'] ?? '';

        if ($this->editingId === null) {
            Currency::query()->create($data);
            session()->flash('status', 'Currency created.');
        } else {
            $currency = Currency::query()->findOrFail($this->editingId);
            $previousCode = $currency->code;
            $currency->update($data);
            $catalog->forget($previousCode);
            $catalog->forget($data['code']);
            session()->flash('status', 'Currency updated.');
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.currencies.index', [
            'currencies' => Currency::query()->orderBy('code')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => 'Currencies',
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
        $this->is_active = true;
        $this->resetValidation();
    }
}

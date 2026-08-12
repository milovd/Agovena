<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Taxes;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\TaxRate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public int $rate_bps = 2100;

    public string $country = '';

    public string $region = '';

    public bool $is_active = true;

    public bool $applies_to_shipping = false;

    public function mount(): void
    {
        $this->authorize('taxes.view');
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
        $this->applies_to_shipping = $rate->applies_to_shipping;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('taxes.manage');
        $this->country = strtoupper(trim($this->country));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_bps' => ['required', 'integer', 'min:0', 'max:100000'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'applies_to_shipping' => ['boolean'],
        ]);
        $data['country'] = $data['country'] ?: null;
        $data['region'] = $data['region'] ?: null;

        TaxRate::query()->updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('status', __($this->editingId ? 'admin.taxes.updated' : 'admin.taxes.created'));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('taxes.manage');
        TaxRate::query()->findOrFail($id)->delete();
        session()->flash('status', __('admin.taxes.deleted'));
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.taxes.index', [
            'rates' => TaxRate::query()->orderBy('name')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.taxes.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'country', 'region', 'applies_to_shipping']);
        $this->rate_bps = 2100;
        $this->is_active = true;
        $this->resetValidation();
    }
}

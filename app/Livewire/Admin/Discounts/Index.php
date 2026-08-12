<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Discounts;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\DiscountCode;
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

    public string $type = 'percent';

    public int $value = 10;

    public string $currency = 'EUR';

    public string $starts_at = '';

    public string $ends_at = '';

    public ?int $max_uses = null;

    public ?int $max_uses_per_customer = null;

    public int $min_subtotal_amount = 0;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('discounts.view');
    }

    public function create(): void
    {
        $this->authorize('discounts.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('discounts.manage');
        $discount = DiscountCode::query()->findOrFail($id);
        $this->editingId = $discount->id;
        $this->code = $discount->code;
        $this->type = $discount->type;
        $this->value = $discount->value;
        $this->currency = $discount->currency ?: 'EUR';
        $this->starts_at = $discount->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $discount->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->max_uses = $discount->max_uses;
        $this->max_uses_per_customer = $discount->max_uses_per_customer;
        $this->min_subtotal_amount = $discount->min_subtotal_amount;
        $this->is_active = $discount->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('discounts.manage');
        $this->code = strtoupper(trim($this->code));
        $this->currency = strtoupper(trim($this->currency));
        $data = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('discount_codes', 'code')->ignore($this->editingId)],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'integer', 'min:0', $this->type === 'percent' ? 'max:100' : 'max:4294967295'],
            'currency' => [$this->type === 'fixed' ? 'required' : 'nullable', 'string', 'size:3', 'alpha'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'min_subtotal_amount' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $data['currency'] = $data['type'] === 'fixed' ? $data['currency'] : null;
        $data['starts_at'] = $data['starts_at'] ?: null;
        $data['ends_at'] = $data['ends_at'] ?: null;

        DiscountCode::query()->updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('status', __($this->editingId ? 'admin.discounts.updated' : 'admin.discounts.created'));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('discounts.manage');
        $discount = DiscountCode::query()->findOrFail($id);
        if ($discount->redemptions()->exists()) {
            $this->addError('delete', __('admin.discounts.delete_used'));

            return;
        }
        $discount->delete();
        session()->flash('status', __('admin.discounts.deleted'));
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.discounts.index', [
            'discounts' => DiscountCode::query()->withCount('redemptions')->orderBy('code')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.discounts.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm', 'editingId', 'code', 'starts_at', 'ends_at', 'max_uses',
            'max_uses_per_customer', 'min_subtotal_amount',
        ]);
        $this->type = 'percent';
        $this->value = 10;
        $this->currency = 'EUR';
        $this->is_active = true;
        $this->resetValidation();
    }
}

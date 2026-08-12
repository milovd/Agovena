<?php

declare(strict_types=1);

namespace App\Livewire\Admin\PlanChanges;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\Product;
use App\Models\ProductPlanChange;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public int|string $from_product_id = '';

    public int|string $to_product_id = '';

    public string $change_type = 'upgrade';

    public string $timing = 'immediate';

    public bool $is_active = true;

    public int $sort = 0;

    public function mount(): void
    {
        $this->authorize('plan-changes.view');
    }

    public function save(): void
    {
        $this->authorize('plan-changes.manage');
        $data = $this->validate([
            'from_product_id' => ['required', 'integer', 'different:to_product_id', Rule::exists('products', 'id')],
            'to_product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'change_type' => ['required', Rule::in(['upgrade', 'downgrade', 'switch'])],
            'timing' => ['required', Rule::in(['immediate', 'next_period'])],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0'],
        ]);

        ProductPlanChange::query()->updateOrCreate([
            'from_product_id' => $data['from_product_id'],
            'to_product_id' => $data['to_product_id'],
        ], $data);

        session()->flash('status', __('admin.plan_changes.saved'));
        $this->reset(['to_product_id', 'sort']);
    }

    public function delete(int $id): void
    {
        $this->authorize('plan-changes.manage');
        ProductPlanChange::query()->findOrFail($id)->delete();
        session()->flash('status', __('admin.plan_changes.deleted'));
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.plan-changes.index', [
            'products' => Product::query()->active()->with('capabilities')->orderBy('name')->get(),
            'changes' => ProductPlanChange::query()
                ->with(['fromProduct', 'toProduct'])
                ->orderBy('from_product_id')
                ->orderBy('sort')
                ->get(),
        ])->layout('layouts.admin', [
            'title' => __('admin.plan_changes.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

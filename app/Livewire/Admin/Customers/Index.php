<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Enums\OrderStatus;
use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.customers.index', [
            'customers' => Customer::query()
                ->with(['user', 'creditAccount'])
                ->withCount('orders')
                ->withSum([
                    'orders as paid_orders_total' => fn ($query) => $query->where('status', OrderStatus::Paid),
                ], 'total_amount')
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($nested) use ($term): void {
                        $nested->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                })
                ->when($this->status === 'anonymized', fn ($query) => $query->whereNotNull('anonymized_at'))
                ->when($this->status === 'deletion', fn ($query) => $query->whereNotNull('deletion_requested_at')->whereNull('anonymized_at'))
                ->when($this->status === 'active', fn ($query) => $query->whereNull('anonymized_at')->whereNull('deletion_requested_at'))
                ->latest('id')
                ->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.customers.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

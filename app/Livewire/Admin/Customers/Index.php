<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.customers.index', [
            'customers' => Customer::query()
                ->with('user')
                ->when($this->search !== '', fn ($query) => $query
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'))
                ->latest('id')
                ->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.customers.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('orders.view');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $orders = Order::query()->orderByDesc('id')->paginate(15);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Orders',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

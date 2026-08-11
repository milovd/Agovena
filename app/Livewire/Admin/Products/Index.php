<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('products.view');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $products = Product::query()->orderByDesc('id')->paginate(15);

        return view('livewire.admin.products.index', [
            'products' => $products,
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Products',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

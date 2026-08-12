<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

final class OrdersIndex extends Component
{
    use WithPagination;

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('payment')
            ->latest('id')
            ->paginate(10);

        return view($theme->view('account.orders.index'), [
            'theme' => $theme,
            'orders' => $orders,
            'accountSection' => 'orders',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.orders_title'),
            'theme' => $theme,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Dashboard extends Component
{
    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = Auth::guard('customer')->user();
        $recentOrders = Order::query()
            ->where('customer_id', $customer?->id)
            ->with('payment')
            ->latest('id')
            ->limit(5)
            ->get();

        return view($theme->view('account.dashboard'), [
            'theme' => $theme,
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'accountSection' => 'overview',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.overview_title'),
            'theme' => $theme,
        ]);
    }
}

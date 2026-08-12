<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Customer\CustomerAccountOverview;
use App\Agovena\Theme\ThemeManager;
use App\Models\Invoice;
use App\Models\Order;
use Livewire\Component;

final class Dashboard extends Component
{
    public function render(ThemeManager $themes, CustomerAccountOverview $overview)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        $recentOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('payment')
            ->latest('id')
            ->limit(5)
            ->get();
        $recentInvoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(3)
            ->get();

        return view($theme->view('account.dashboard'), [
            'theme' => $theme,
            'customer' => $customer,
            'emailVerified' => $customer->hasVerifiedEmail(),
            'overviewCards' => $overview->cardsFor($customer),
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'accountSection' => 'overview',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.overview_title'),
            'theme' => $theme,
        ]);
    }
}

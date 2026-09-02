<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Customer\CustomerAccountOverview;
use App\Agovena\Referrals\ReferralService;
use App\Agovena\Theme\ThemeManager;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use Livewire\Component;

final class Dashboard extends Component
{
    public function render(ThemeManager $themes, CustomerAccountOverview $overview, ReferralService $referrals)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        $recentOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['payment', 'items.product.images'])
            ->latest('id')
            ->limit(5)
            ->get();
        $recentInvoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(3)
            ->get();
        $unpaidOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Pending)
            ->whereHas('payment', function ($query): void {
                $query->whereIn('status', [
                    PaymentStatus::Pending,
                    PaymentStatus::Failed,
                    PaymentStatus::Cancelled,
                ]);
            })
            ->with('payment')
            ->latest('id')
            ->limit(8)
            ->get();
        $referralCode = $customer->referralCodes()->latest('id')->first();

        return view($theme->view('account.dashboard'), [
            'theme' => $theme,
            'customer' => $customer,
            'emailVerified' => $customer->hasVerifiedEmail(),
            'overviewCards' => $overview->cardsFor($customer),
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'unpaidOrders' => $unpaidOrders,
            'referralsEnabled' => $referrals->isEnabled(),
            'referralCode' => $referralCode,
            'referralRewardPercentage' => $referrals->defaultRewardPercentage(),
            'accountSection' => 'overview',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.overview_title'),
            'theme' => $theme,
        ]);
    }
}

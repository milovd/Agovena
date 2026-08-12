<?php

declare(strict_types=1);

namespace Agovena\Modules\Subscriptions\Http\Livewire\Customer;

use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class SubscriptionsIndex extends Component
{
    public function cancel(int $id, SubscriptionService $subscriptions): void
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $subscription = Subscription::query()
            ->whereKey($id)
            ->where(function ($q) use ($customer): void {
                $q->where('customer_id', $customer->id)
                    ->orWhere('customer_email', $customer->email);
            })
            ->firstOrFail();

        $subscriptions->cancel($subscription, atPeriodEnd: true);
        session()->flash('status', __('subscriptions::customer.cancelled'));
    }

    public function render(ThemeManager $themes)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $subscriptions = Subscription::query()
            ->with('product')
            ->where(function ($q) use ($customer): void {
                $q->where('customer_id', $customer->id)
                    ->orWhere('customer_email', $customer->email);
            })
            ->orderByDesc('id')
            ->get();

        $theme = $themes->active();

        return view($theme->view('account.subscriptions'), [
            'theme' => $theme,
            'subscriptions' => $subscriptions,
            'accountSection' => 'subscriptions',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('subscriptions::customer.title'),
            'theme' => $theme,
        ]);
    }
}

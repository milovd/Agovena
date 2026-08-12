<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Fulfillment\OrderFulfillmentPresenter;
use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class OrderShow extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $customer = Auth::guard('customer')->user();

        abort_unless(
            $customer !== null && (int) $order->customer_id === (int) $customer->id,
            404,
        );

        $this->order = $order->load(['items', 'payment']);
    }

    public function render(ThemeManager $themes, OrderFulfillmentPresenter $fulfillment)
    {
        $theme = $themes->active();

        return view($theme->view('account.orders.show'), [
            'theme' => $theme,
            'order' => $this->order,
            'shipments' => $fulfillment->forOrder($this->order),
            'accountSection' => 'orders',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.order_title', ['number' => $this->order->number]),
            'theme' => $theme,
        ]);
    }
}

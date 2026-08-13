<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Fulfillment\OrderFulfillmentPresenter;
use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Livewire\Component;

final class OrderShow extends Component
{
    use PaysUnpaidOrders;

    public Order $order;

    public function mount(Order $order): void
    {
        $customer = authenticated_customer();

        abort_unless(
            (int) $order->customer_id === (int) $customer->id,
            404,
        );

        $this->order = $order->load(['items', 'payment', 'invoice', 'creditNotes', 'refunds']);
    }

    protected function unpaidOrder(): ?Order
    {
        return $this->order->isAwaitingPayment() ? $this->order : null;
    }

    protected function afterPaymentAttempt(Order $order): void
    {
        $this->order = $order->load(['items', 'payment', 'invoice', 'creditNotes', 'refunds']);
    }

    public function render(ThemeManager $themes, OrderFulfillmentPresenter $fulfillment)
    {
        $theme = $themes->active();

        return view($theme->view('account.orders.show'), [
            'theme' => $theme,
            'order' => $this->order,
            'shipments' => $fulfillment->forOrder($this->order),
            'accountSection' => 'orders',
            'paymentOptions' => $this->paymentGatewayOptions(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.order_title', ['number' => $this->order->number]),
            'theme' => $theme,
        ]);
    }
}

<div class="store-confirmation" @if ($shouldPoll) wire:poll.5s="refresh" @endif>
    <h1 class="store-title">{{ __('storefront.payment_status.title.'.$state) }}</h1>
    <p class="store-lede">{{ __('storefront.payment_status.lede.'.$state, ['number' => $order->number]) }}</p>

    @if ($state === 'pending')
        <p class="store-note" role="status">{{ __('storefront.payment_status.waiting') }}</p>
    @endif

    @if (in_array($state, ['failed', 'cancelled', 'expired'], true) && $order->isAwaitingPayment())
        <p>
            <a class="store-btn" href="{{ route('storefront.order.confirmation', $order) }}">{{ __('storefront.payment_status.view_order') }}</a>
        </p>
        @auth
            <p>
                <a class="store-btn store-btn--secondary" href="{{ route('customer.orders.show', $order) }}">{{ __('storefront.payment_status.retry') }}</a>
            </p>
        @endauth
    @elseif ($state === 'paid')
        <p>
            <a class="store-btn" href="{{ route('storefront.order.confirmation', $order) }}">{{ __('storefront.payment_status.view_order') }}</a>
        </p>
    @else
        <p>
            <a class="store-btn store-btn--secondary" href="{{ route('storefront.order.confirmation', $order) }}">{{ __('storefront.payment_status.view_order') }}</a>
        </p>
    @endif
</div>

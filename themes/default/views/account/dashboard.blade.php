<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.account.welcome', ['name' => $customer->name]) }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.account.overview_lede') }}</p>
        </header>

        <div class="store-account-panel__section">
            <div class="store-account-panel__section-head">
                <h2>{{ __('customer.account.recent_orders') }}</h2>
                <a href="{{ route('customer.orders.index') }}">{{ __('customer.account.view_all_orders') }}</a>
            </div>

            @if ($recentOrders->isEmpty())
                <p class="store-account-panel__empty">{{ __('customer.account.no_orders') }}</p>
                <a class="store-btn store-btn--outline" href="{{ route('storefront.home') }}">{{ __('customer.account.browse_catalog') }}</a>
            @else
                <ul class="store-order-list" role="list">
                    @foreach ($recentOrders as $order)
                        <li class="store-order-list__item">
                            <div>
                                <a class="store-order-list__number" href="{{ route('customer.orders.show', $order) }}">{{ $order->number }}</a>
                                <p class="store-order-list__meta">{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                            </div>
                            <div class="store-order-list__status">
                                <span>{{ __('customer.account.order_statuses.'.$order->status->value) }}</span>
                                <strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>

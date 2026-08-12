<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.orders.index') }}">{{ __('customer.account.back_to_orders') }}</a>
            </p>
            <h1 class="store-account-panel__title">{{ $order->number }}</h1>
            <p class="store-account-panel__lede">
                {{ __('customer.account.order_statuses.'.$order->status->value) }}
                ·
                {{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}
            </p>
        </header>

        <div class="store-account-panel__grid">
            <div>
                <h2>{{ __('customer.account.items') }}</h2>
                <ul class="store-order-items" role="list">
                    @foreach ($order->items as $item)
                        <li class="store-order-items__row">
                            <div>
                                <strong>{{ $item->label }}</strong>
                                <p>{{ __('customer.account.quantity', ['count' => $item->quantity]) }}</p>
                            </div>
                            <strong>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</strong>
                        </li>
                    @endforeach
                </ul>
                <p class="store-order-items__total">
                    <span>{{ __('customer.account.total') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong>
                </p>
            </div>

            <div>
                <h2>{{ __('customer.account.billing') }}</h2>
                <p>{{ $order->billing_name ?: $order->customer_name }}</p>
                @if ($order->billing_company)<p>{{ $order->billing_company }}</p>@endif
                @if ($order->billing_line1)
                    <p>{{ $order->billing_line1 }}</p>
                    @if ($order->billing_line2)<p>{{ $order->billing_line2 }}</p>@endif
                    <p>{{ $order->billing_postal_code }} {{ $order->billing_city }}</p>
                    <p>{{ $order->billing_country }}</p>
                @endif
                <p>{{ $order->customer_email }}</p>
                <p>{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
            </div>
        </div>
    </section>
</div>

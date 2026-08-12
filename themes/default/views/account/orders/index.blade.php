<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.account.orders_title') }}</h1>
        </header>

        @if ($orders->isEmpty())
            <p class="store-account-panel__empty">{{ __('customer.account.no_orders') }}</p>
            <a class="store-btn store-btn--outline" href="{{ route('storefront.home') }}">{{ __('customer.account.browse_catalog') }}</a>
        @else
            <div class="store-table-wrap">
                <table class="store-table">
                    <thead>
                        <tr>
                            <th>{{ __('customer.account.order_number') }}</th>
                            <th>{{ __('customer.account.order_date') }}</th>
                            <th>{{ __('customer.account.order_status') }}</th>
                            <th>{{ __('customer.account.payment_status') }}</th>
                            <th>{{ __('customer.account.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>{{ $order->number }}</td>
                                <td>{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td>{{ __('customer.account.order_statuses.'.$order->status->value) }}</td>
                                <td>{{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}</td>
                                <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                                <td>
                                    <a href="{{ route('customer.orders.show', $order) }}">{{ __('customer.account.view_order') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="store-account-panel__pagination">
                {{ $orders->links() }}
            </div>
        @endif
    </section>
</div>

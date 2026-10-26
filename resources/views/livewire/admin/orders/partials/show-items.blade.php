{{-- Order detail: line items and totals. Part of livewire.admin.orders.show. --}}
<section class="ag-card" aria-labelledby="order-items-heading">
    <header class="ag-card__header">
        <x-ag.icon-tile name="package" tone="blue" />
        <h2 id="order-items-heading" class="ag-card__title">{{ __('admin.orders.show.items') }}</h2>
        <p class="ag-card__description">{{ __('admin.orders.show.items_lede') }}</p>
    </header>
    <div class="ag-card__content">
        <div class="ag-table-wrap" role="region" aria-label="{{ __('admin.orders.show.items') }}" tabindex="0">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col">{{ __('common.quantity') }}</th>
                        <th scope="col">{{ __('admin.orders.show.unit') }}</th>
                        <th scope="col">{{ __('admin.orders.show.line') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        @php($subscription = $subscriptionsByItem->get($item->id))
                        <tr>
                            <td>
                                <div class="admin-order__line">
                                    <span>{{ $item->label }}</span>
                                    @if ($subscription)
                                        <span class="admin-order__line-meta">
                                            <span class="ag-badge ag-badge--info">{{ __('admin.orders.inline.recurring') }}</span>
                                            <span>{{ $subscription->interval_count }} × {{ __('subscriptions::interval.'.$subscription->interval->value) }}</span>
                                            @if ($canViewSubscriptions)
                                                <a href="{{ route('admin.subscriptions.show', $subscription) }}">{{ __('admin.orders.inline.subscription', ['number' => $subscription->number]) }}</a>
                                            @else
                                                <span>{{ __('admin.orders.inline.subscription', ['number' => $subscription->number]) }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($item->unit_amount, $item->currency) }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <dl class="admin-order__totals">
            <div>
                <dt>{{ __('common.subtotal') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $order->currency) }}</dd>
            </div>
            @if (($order->shipping_amount ?? 0) > 0 || filled($order->shipping_method_label))
                <div>
                    <dt>
                        {{ __('common.shipping') }}
                        @if ($order->shipping_method_label)
                            <span class="ag-muted">({{ $order->shipping_method_label }})</span>
                        @endif
                    </dt>
                    <dd>{{ \App\Support\MoneyFormatter::format((int) $order->shipping_amount, $order->currency) }}</dd>
                </div>
            @endif
            <div class="admin-order__total">
                <dt>{{ __('common.total') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</dd>
            </div>
        </dl>
    </div>
</section>

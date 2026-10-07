{{-- Order detail sidebar: totals summary. Part of livewire.admin.orders.show. --}}
<section class="ag-section" aria-labelledby="order-summary-heading">
    <header class="ag-section__header">
        <h3 id="order-summary-heading" class="ag-section__title">{{ __('admin.orders.show.summary') }}</h3>
    </header>
    <div class="ag-section__body">
        <dl class="ag-dl">
            <div>
                <dt>{{ __('admin.orders.status_label') }}</dt>
                <dd>
                    <span @class([
                        'ag-badge',
                        'ag-badge--success' => $order->status->value === 'paid',
                        'ag-badge--warning' => $order->status->value === 'pending',
                        'ag-badge--muted' => $order->status->value === 'cancelled',
                    ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                </dd>
            </div>
            <div>
                <dt>{{ __('common.created') }}</dt>
                <dd>{{ $order->created_at?->toDayDateTimeString() }}</dd>
            </div>
            <div>
                <dt>{{ __('common.subtotal') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $order->currency) }}</dd>
            </div>
            @if (($order->shipping_amount ?? 0) > 0 || filled($order->shipping_method_label))
                <div>
                    <dt>{{ __('common.shipping') }}</dt>
                    <dd>
                        {{ \App\Support\MoneyFormatter::format((int) $order->shipping_amount, $order->currency) }}
                        @if ($order->shipping_method_label)
                            <span class="ag-muted">({{ $order->shipping_method_label }})</span>
                        @endif
                    </dd>
                </div>
            @endif
            <div>
                <dt>{{ __('common.total') }}</dt>
                <dd><strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong></dd>
            </div>
        </dl>
    </div>
</section>

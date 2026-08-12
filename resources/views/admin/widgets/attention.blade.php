<ul class="ag-attention" role="list">
    @if ($productCount === 0)
        <li class="ag-attention__item">
            {{ __('admin.dashboard.attention.no_products') }}
            @can('products.create')
                <a href="{{ route('admin.products.create') }}">{{ __('admin.dashboard.attention.create_product') }}</a>
            @endcan
        </li>
    @endif
    @if ($pendingPaymentCount > 0)
        <li class="ag-attention__item">
            {{ trans_choice('admin.dashboard.attention.pending_payments', $pendingPaymentCount, ['count' => $pendingPaymentCount]) }}
            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}">{{ __('admin.dashboard.attention.review_orders') }}</a>
            @endcan
        </li>
    @endif
    @if ($productCount > 0 && $pendingPaymentCount === 0)
        <li class="ag-attention__item ag-attention__item--ok">{{ __('admin.dashboard.attention.all_clear') }}</li>
    @endif
</ul>

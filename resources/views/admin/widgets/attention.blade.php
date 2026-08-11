<ul class="ag-attention" role="list">
    @if ($productCount === 0)
        <li class="ag-attention__item">
            No products yet.
            @can('products.create')
                <a href="{{ route('admin.products.create') }}">Create a product</a>
            @endcan
        </li>
    @endif
    @if ($pendingPaymentCount > 0)
        <li class="ag-attention__item">
            {{ $pendingPaymentCount }} pending {{ \Illuminate\Support\Str::plural('payment', $pendingPaymentCount) }}.
            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}">Review orders</a>
            @endcan
        </li>
    @endif
    @if ($productCount > 0 && $pendingPaymentCount === 0)
        <li class="ag-attention__item ag-attention__item--ok">Nothing needs attention right now.</li>
    @endif
</ul>

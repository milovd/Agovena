<div class="ag-stats" role="list">
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">Products</p>
        <p class="ag-stats__value">{{ $productCount }}</p>
        <p class="ag-stats__hint">{{ $activeProductCount }} active</p>
    </div>
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">Orders</p>
        <p class="ag-stats__value">{{ $orderCount }}</p>
    </div>
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">Paid revenue</p>
        @if ($paidRevenueByCurrency->isEmpty())
            <p class="ag-stats__value">—</p>
            <p class="ag-stats__hint">No paid payments yet</p>
        @else
            @foreach ($paidRevenueByCurrency as $currency => $amount)
                <p class="ag-stats__value">{{ \App\Support\MoneyFormatter::format((int) $amount, (string) $currency) }}</p>
            @endforeach
            <p class="ag-stats__hint">Sum of paid payments</p>
        @endif
    </div>
</div>

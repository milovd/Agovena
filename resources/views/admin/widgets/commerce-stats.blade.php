<div class="ag-stats" role="list">
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">{{ __('admin.dashboard.stats.products') }}</p>
        <p class="ag-stats__value">{{ $productCount }}</p>
        <p class="ag-stats__hint">{{ __('admin.dashboard.stats.products_active', ['count' => $activeProductCount]) }}</p>
    </div>
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">{{ __('admin.dashboard.stats.orders') }}</p>
        <p class="ag-stats__value">{{ $orderCount }}</p>
    </div>
    <div class="ag-stats__item" role="listitem">
        <p class="ag-stats__label">{{ __('admin.dashboard.stats.paid_revenue') }}</p>
        @if ($paidRevenueByCurrency->isEmpty())
            <p class="ag-stats__value">{{ __('common.em_dash') }}</p>
            <p class="ag-stats__hint">{{ __('admin.dashboard.stats.no_paid_payments') }}</p>
        @else
            @foreach ($paidRevenueByCurrency as $currency => $amount)
                <p class="ag-stats__value">{{ \App\Support\MoneyFormatter::format((int) $amount, (string) $currency) }}</p>
            @endforeach
            <p class="ag-stats__hint">{{ __('admin.dashboard.stats.paid_payments_sum') }}</p>
        @endif
    </div>
</div>

<section class="admin-dashboard" aria-label="{{ __('admin.dashboard.aria') }}">
    @forelse ($widgets as $widget)
        <section class="admin-panel" aria-labelledby="widget-{{ $widget->id }}">
            <h2 id="widget-{{ $widget->id }}" class="admin-panel__title">{{ __($widget->label) }}</h2>
            @include($widget->view, [
                'productCount' => $productCount,
                'activeProductCount' => $activeProductCount,
                'orderCount' => $orderCount,
                'pendingPaymentCount' => $pendingPaymentCount,
                'paidRevenueByCurrency' => $paidRevenueByCurrency,
                'recentOrders' => $recentOrders,
            ])
        </section>
    @empty
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.dashboard.empty_title') }}</p>
            <p class="ag-empty__text">{{ __('admin.dashboard.empty_text') }}</p>
        </div>
    @endforelse
</section>

<section class="admin-dashboard" aria-label="Dashboard">
    @forelse ($widgets as $widget)
        <section class="admin-panel" aria-labelledby="widget-{{ $widget->id }}">
            <h2 id="widget-{{ $widget->id }}" class="admin-panel__title">{{ $widget->label }}</h2>
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
            <p class="ag-empty__title">No dashboard widgets</p>
            <p class="ag-empty__text">Widgets appear here when registered and permitted.</p>
        </div>
    @endforelse
</section>

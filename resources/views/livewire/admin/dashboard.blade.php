<section class="admin-dashboard" aria-label="{{ __('admin.dashboard.aria') }}">
    @if ($gettingStarted !== [])
        <section class="admin-panel" aria-labelledby="getting-started-heading">
            <div class="ag-toolbar" style="justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2 id="getting-started-heading" class="admin-panel__title">{{ __('admin.dashboard.getting_started.title') }}</h2>
                    <p class="ag-muted">{{ __('admin.dashboard.getting_started.lede') }}</p>
                </div>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="dismissGettingStarted">
                    {{ __('admin.dashboard.getting_started.dismiss') }}
                </button>
            </div>
            <ul class="ag-attention" role="list">
                @foreach ($gettingStarted as $item)
                    <li class="ag-attention__item @if ($item->done) ag-attention__item--ok @endif" wire:key="getting-started-{{ $item->id }}">
                        @if ($item->done)
                            {{ __($item->labelKey) }}
                            <span class="visually-hidden">{{ __('admin.dashboard.getting_started.done') }}</span>
                        @else
                            <a href="{{ $item->href }}">{{ __($item->labelKey) }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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

<section class="admin-dashboard" aria-label="{{ __('admin.dashboard.aria') }}">
    <x-ag.page-header :heading="__('admin.dashboard.heading')" :lede="__('admin.dashboard.lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('storefront.home') }}" target="_blank" rel="noopener noreferrer">
                <x-ag.icon name="external-link" :size="16" />
                {{ __('admin.view_storefront') }}
            </a>
        </x-slot:actions>
    </x-ag.page-header>

    @if ($gettingStarted !== [])
        <section class="admin-panel" aria-labelledby="getting-started-heading">
            <div class="ag-toolbar" style="justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2 id="getting-started-heading" class="admin-panel__title" style="margin-bottom:0.35rem;">{{ __('admin.dashboard.getting_started.title') }}</h2>
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

    <div class="ag-metrics" role="list">
        @foreach ($metrics as $metric)
            <article class="ag-metric" role="listitem" wire:key="metric-{{ $metric['id'] }}">
                <p class="ag-metric__label">{{ $metric['label'] }}</p>
                <p class="ag-metric__value">{{ $metric['value'] }}</p>
                @if ($metric['hint'])
                    <p class="ag-metric__hint">{{ $metric['hint'] }}</p>
                @endif
                @if ($metric['href'])
                    <a class="ag-metric__link" href="{{ $metric['href'] }}">{{ __('admin.dashboard.view_details') }}</a>
                @endif
            </article>
        @endforeach
    </div>

    <div class="ag-dashboard-grid">
        <div class="ag-dashboard-stack">
            <section
                class="ag-chart-card"
                aria-labelledby="revenue-chart-title"
                x-data="agChart(@js([
                    'type' => 'line',
                    'labels' => $revenueSeries['labels'],
                    'datasets' => [[
                        'label' => __('admin.dashboard.charts.revenue'),
                        'data' => $revenueSeries['values'],
                        'borderColor' => '#155eef',
                        'backgroundColor' => 'rgba(21, 94, 239, 0.12)',
                        'fill' => true,
                        'tension' => 0.35,
                    ]],
                ]))"
            >
                <header class="ag-chart-card__header">
                    <div>
                        <h2 id="revenue-chart-title" class="ag-chart-card__title">{{ __('admin.dashboard.charts.revenue') }}</h2>
                        <p class="ag-chart-card__lede">{{ __('admin.dashboard.charts.revenue_lede') }}</p>
                    </div>
                </header>
                @if (array_sum($revenueSeries['values']) === 0)
                    <div class="ag-empty" role="status">
                        <p class="ag-empty__title">{{ __('admin.dashboard.charts.empty_title') }}</p>
                        <p class="ag-empty__text">{{ __('admin.dashboard.charts.empty_revenue') }}</p>
                    </div>
                @else
                    <div class="ag-chart-card__canvas">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('admin.dashboard.charts.revenue') }}"></canvas>
                    </div>
                @endif
            </section>

            <section
                class="ag-chart-card"
                aria-labelledby="orders-chart-title"
                x-data="agChart(@js([
                    'type' => 'bar',
                    'labels' => $orderSeries['labels'],
                    'datasets' => [[
                        'label' => __('admin.dashboard.charts.orders'),
                        'data' => $orderSeries['values'],
                        'backgroundColor' => 'rgba(21, 94, 239, 0.65)',
                        'borderRadius' => 4,
                    ]],
                ]))"
            >
                <header class="ag-chart-card__header">
                    <div>
                        <h2 id="orders-chart-title" class="ag-chart-card__title">{{ __('admin.dashboard.charts.orders') }}</h2>
                        <p class="ag-chart-card__lede">{{ __('admin.dashboard.charts.orders_lede') }}</p>
                    </div>
                </header>
                @if (array_sum($orderSeries['values']) === 0)
                    <div class="ag-empty" role="status">
                        <p class="ag-empty__title">{{ __('admin.dashboard.charts.empty_title') }}</p>
                        <p class="ag-empty__text">{{ __('admin.dashboard.charts.empty_orders') }}</p>
                    </div>
                @else
                    <div class="ag-chart-card__canvas">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('admin.dashboard.charts.orders') }}"></canvas>
                    </div>
                @endif
            </section>
        </div>

        <div class="ag-dashboard-stack">
            <section class="admin-panel" aria-labelledby="attention-heading">
                <h2 id="attention-heading" class="admin-panel__title">{{ __('admin.dashboard.attention.title') }}</h2>
                <ul class="ag-attention" role="list">
                    @forelse ($attentionItems as $item)
                        <li class="ag-attention__item" wire:key="attention-{{ $loop->index }}">
                            {{ $item['label'] }}
                            <a href="{{ $item['href'] }}">{{ __('admin.dashboard.attention.review') }}</a>
                        </li>
                    @empty
                        <li class="ag-attention__item ag-attention__item--ok">{{ __('admin.dashboard.attention.all_clear') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="admin-panel" aria-labelledby="recent-orders-heading">
                <h2 id="recent-orders-heading" class="admin-panel__title">{{ __('admin.dashboard.widgets.recent_orders') }}</h2>
                @include('admin.widgets.recent-orders', ['recentOrders' => $recentOrders])
            </section>
        </div>
    </div>
</section>

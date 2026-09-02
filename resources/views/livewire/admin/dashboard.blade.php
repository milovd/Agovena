@php
    $supportTicketCount = $supportTicketCount ?? 0;
    $supportTickets = $supportTickets ?? collect();
    $supportTicketsAvailable = $supportTicketsAvailable ?? false;
    $activeUserCount = $activeUserCount ?? 0;
    $activeUsers = $activeUsers ?? [];
    $activeUsersAvailable = $activeUsersAvailable ?? false;
    $activeUsersHasMore = $activeUsersHasMore ?? false;
@endphp

<section class="admin-dashboard" aria-label="{{ __('admin.dashboard.aria') }}">
    <x-ag.page-header :heading="__('admin.dashboard.heading')" :lede="__('admin.dashboard.lede')" />

    @if ($gettingStarted !== [])
        @php
            $gettingStartedTotal = count($gettingStarted);
            $gettingStartedDone = collect($gettingStarted)->where('done', true)->count();
        @endphp
        <section
            class="ag-checklist"
            x-data="{ open: false }"
            :class="{ 'ag-checklist--open': open }"
            aria-labelledby="getting-started-heading"
        >
            <header class="ag-checklist__header">
                <button
                    type="button"
                    class="ag-checklist__toggle"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="getting-started-items"
                    :aria-label="open ? @js(__('admin.dashboard.getting_started.collapse')) : @js(__('admin.dashboard.getting_started.expand'))"
                >
                    <span class="ag-checklist__toggle-icon" aria-hidden="true">
                        <x-ag.icon name="chevron-down" :size="20" />
                    </span>
                    <span class="ag-checklist__toggle-text">
                        <span id="getting-started-heading" class="ag-checklist__title">{{ __('admin.dashboard.getting_started.title') }}</span>
                        <span class="ag-checklist__progress">{{ __('admin.dashboard.getting_started.progress', ['done' => $gettingStartedDone, 'total' => $gettingStartedTotal]) }}</span>
                    </span>
                </button>
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="dismissGettingStarted">
                    {{ __('admin.dashboard.getting_started.dismiss') }}
                </button>
            </header>
            <ul id="getting-started-items" class="ag-checklist__items" role="list" x-show="open" x-cloak>
                @foreach ($gettingStarted as $item)
                    <li class="ag-checklist__item @if ($item->done) ag-checklist__item--done @endif" wire:key="getting-started-{{ $item->id }}">
                        <span class="ag-checklist__status" aria-hidden="true">
                            <x-ag.icon :name="$item->done ? 'check' : 'circle'" :size="17" />
                        </span>
                        <span class="ag-checklist__content">
                            <span class="ag-checklist__item-title">{{ __($item->labelKey) }}</span>
                            @if ($item->descriptionKey)
                                <span class="ag-checklist__description">{{ __($item->descriptionKey) }}</span>
                            @endif
                            @if ($item->done)
                                <span class="visually-hidden">{{ __('admin.dashboard.getting_started.done') }}</span>
                            @endif
                        </span>
                        @if (! $item->done)
                            <a class="ag-checklist__action" href="{{ $item->href }}" aria-label="{{ __($item->labelKey) }}">
                                <x-ag.icon name="chevron-right" :size="17" />
                            </a>
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
                wire:key="dashboard-chart-{{ $chartRange }}-{{ $chartType }}"
                aria-labelledby="dashboard-chart-title"
                x-data="agChart(@js([
                    'type' => $chartType,
                    'showLegend' => true,
                    'dualAxis' => true,
                    'labels' => $revenueSeries['labels'],
                    'axisLabels' => [
                        'revenue' => __('admin.dashboard.charts.revenue_axis'),
                        'orders' => __('admin.dashboard.charts.orders_axis'),
                    ],
                    'datasets' => [
                        [
                            'label' => __('admin.dashboard.charts.revenue'),
                            'data' => $revenueSeries['values'],
                            'yAxisID' => 'y',
                            'borderColor' => 'var(--ag-color-chart-1)',
                            'backgroundColor' => 'var(--ag-color-primary-soft)',
                            'pointBackgroundColor' => 'var(--ag-color-chart-1)',
                            'fill' => true,
                            'tension' => 0.3,
                            'borderWidth' => 2.5,
                            'pointRadius' => 0,
                            'pointHoverRadius' => 4,
                            'pointHoverBackgroundColor' => 'var(--ag-color-chart-1)',
                        ],
                        [
                            'label' => __('admin.dashboard.charts.orders'),
                            'data' => $orderSeries['values'],
                            'yAxisID' => 'y1',
                            'borderColor' => 'var(--ag-color-chart-4)',
                            'backgroundColor' => 'var(--ag-color-chart-4)',
                            'pointBackgroundColor' => 'var(--ag-color-chart-4)',
                            'fill' => false,
                            'tension' => 0.3,
                            'borderWidth' => 2.5,
                            'pointRadius' => 0,
                            'pointHoverRadius' => 4,
                            'pointHoverBackgroundColor' => 'var(--ag-color-chart-4)',
                            'borderRadius' => 5,
                        ],
                    ],
                ]))"
            >
                <header class="ag-chart-card__header">
                    <div>
                        <p class="ag-chart-card__eyebrow">{{ __('admin.dashboard.charts.eyebrow') }}</p>
                        <h2 id="dashboard-chart-title" class="ag-chart-card__title">{{ __('admin.dashboard.charts.overview') }}</h2>
                        <p class="ag-chart-card__lede">{{ __('admin.dashboard.charts.overview_lede') }}</p>
                    </div>
                    <div class="ag-chart-card__toolbar">
                        <div class="ag-chart-control">
                            <span id="dashboard-chart-range-label" class="ag-chart-control__label">{{ __('admin.dashboard.charts.range_label') }}</span>
                            <div class="ag-tabs" role="group" aria-labelledby="dashboard-chart-range-label">
                                @foreach ($chartRanges as $range => $label)
                                    <button
                                        type="button"
                                        class="ag-tabs__tab @if ((string) $chartRange === (string) $range) ag-tabs__tab--active @endif"
                                        aria-pressed="{{ (string) $chartRange === (string) $range ? 'true' : 'false' }}"
                                        wire:click="setChartRange('{{ $range }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="setChartRange"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="ag-chart-control">
                            <label class="ag-chart-control__label" for="dashboard-chart-type">{{ __('admin.dashboard.charts.type_label') }}</label>
                            <select
                                id="dashboard-chart-type"
                                class="ag-select"
                                wire:model.live="chartType"
                                wire:loading.attr="disabled"
                            >
                                @foreach ($chartTypes as $type => $label)
                                    <option value="{{ $type }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </header>
                @if (array_sum($revenueSeries['values']) === 0 && array_sum($orderSeries['values']) === 0)
                    <div class="ag-empty" role="status">
                        <p class="ag-empty__title">{{ __('admin.dashboard.charts.empty_title') }}</p>
                        <p class="ag-empty__text">{{ __('admin.dashboard.charts.empty_overview') }}</p>
                    </div>
                @else
                    <div class="ag-chart-card__canvas ag-chart-card__canvas--legend">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('admin.dashboard.charts.overview') }}"></canvas>
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
                            <span>{{ $item['label'] }}</span>
                            <a class="ag-checklist__action" href="{{ $item['href'] }}">
                                <span>{{ __('admin.dashboard.attention.review') }}</span>
                                <x-ag.icon name="chevron-right" :size="16" />
                            </a>
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

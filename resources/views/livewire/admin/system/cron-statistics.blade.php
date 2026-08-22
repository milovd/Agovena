<div class="admin-page">
    <x-ag.page-header :heading="__('admin.cron_statistics.title')" :lede="__('admin.cron_statistics.lede')" />

    @if ($schedulerRequired)
        <div class="ag-alert ag-alert--warning" role="status">
            <div class="ag-alert__body">
                <p class="ag-alert__title">{{ __('admin.cron_statistics.required_title') }}</p>
                <p class="ag-alert__text">{{ __('admin.cron_statistics.required_text') }}</p>
            </div>
        </div>
    @endif

    <div class="ag-metrics" role="list">
        @foreach ($statusCards as $card)
            <article class="ag-metric ag-metric--status" role="listitem" wire:key="cron-status-{{ $card['key'] }}">
                <p class="ag-metric__label">{{ $card['label'] }}</p>
                <p class="ag-metric__value ag-metric__value--{{ $card['tone'] }}">{{ $card['value'] }}</p>
                @if ($card['hint'])
                    <p class="ag-metric__hint">{{ $card['hint'] }}</p>
                @endif
            </article>
        @endforeach
    </div>

    <section
        class="ag-chart-card"
        aria-labelledby="cron-chart-title"
        wire:key="cron-chart-{{ $range }}"
        x-data="agChart(@js([
            'type' => 'line',
            'labels' => $chart['labels'],
            'datasets' => $chart['datasets'],
            'showLegend' => true,
        ]))"
    >
        <header class="ag-chart-card__header">
            <div>
                <h2 id="cron-chart-title" class="ag-chart-card__title">{{ __('admin.cron_statistics.chart_title') }}</h2>
                <p class="ag-chart-card__lede">{{ __('admin.cron_statistics.chart_lede') }}</p>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="cron-range">{{ __('admin.cron_statistics.range_label') }}</label>
                <select id="cron-range" class="ag-select" wire:model.live="range">
                    <option value="week">{{ __('admin.cron_statistics.range_week') }}</option>
                    <option value="month">{{ __('admin.cron_statistics.range_month') }}</option>
                </select>
            </div>
        </header>
        <div class="ag-chart-card__canvas ag-chart-card__canvas--legend">
            <canvas x-ref="canvas" role="img" aria-label="{{ __('admin.cron_statistics.chart_title') }}"></canvas>
        </div>
    </section>

    <div class="ag-metrics ag-metrics--cron" role="list">
        @foreach ($metricCards as $metric)
            <article class="ag-metric" role="listitem" wire:key="cron-metric-{{ $metric['id'] }}">
                <p class="ag-metric__label">{{ $metric['label'] }}</p>
                <p class="ag-metric__value">{{ number_format($metric['value']) }}</p>
                <p class="ag-metric__hint">{{ $metric['description'] }}</p>
            </article>
        @endforeach
    </div>

    <section class="admin-panel">
        <h3 class="admin-panel__title">{{ __('admin.cron_statistics.tasks_title') }}</h3>
        <p class="ag-muted">{{ __('admin.cron_statistics.tasks_lede') }}</p>
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.cron_statistics.task') }}</th>
                        <th scope="col">{{ __('admin.cron_statistics.frequency') }}</th>
                        <th scope="col">{{ __('admin.cron_statistics.last_run') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr wire:key="cron-task-{{ $task['id'] }}">
                            <td>{{ $task['label'] }}</td>
                            <td>{{ $task['frequency'] }}</td>
                            <td>{{ $task['last_run'] }}</td>
                            <td>
                                @if ($task['active'])
                                    <span class="ag-badge ag-badge--success">{{ __('common.active') }}</span>
                                @else
                                    <span class="ag-badge ag-badge--muted">{{ __('admin.cron_statistics.inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel">
        <h3 class="admin-panel__title">{{ __('admin.cron_statistics.setup_title') }}</h3>
        <p class="ag-muted">{{ __('admin.cron_statistics.setup_lede') }}</p>
        <pre class="ag-code" tabindex="0"><code>{{ $cronCommand }}</code></pre>
        <p class="ag-muted">{{ __('admin.cron_statistics.setup_hint') }}</p>
    </section>
</div>

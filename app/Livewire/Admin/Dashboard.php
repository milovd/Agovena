<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardMetrics;
use App\Agovena\Admin\GettingStartedChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Dashboard extends Component
{
    use AuthorizesRequests;

    public string $chartRange = '14';

    public string $chartType = 'line';

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function dismissGettingStarted(GettingStartedChecklist $checklist): void
    {
        $this->authorize('dashboard.view');
        $checklist->dismiss();
    }

    public function setChartRange(string $range): void
    {
        $this->authorize('dashboard.view');
        abort_unless(in_array($range, ['7', '14', 'month', '90'], true), 404);

        $this->chartRange = $range;
    }

    public function setChartType(string $type): void
    {
        $this->authorize('dashboard.view');
        abort_unless(in_array($type, ['line', 'bar'], true), 404);

        $this->chartType = $type;
    }

    public function render(AdminRegistrar $admin, GettingStartedChecklist $gettingStarted, DashboardMetrics $metrics)
    {
        $data = $metrics->build($this->chartRange);

        return view('livewire.admin.dashboard', [
            'gettingStarted' => $gettingStarted->items(),
            'metrics' => $data['metrics'],
            'revenueSeries' => $data['revenueSeries'],
            'orderSeries' => $data['orderSeries'],
            'chartRange' => $this->chartRange,
            'chartType' => $this->chartType,
            'chartRanges' => [
                '7' => __('admin.dashboard.charts.ranges.7'),
                '14' => __('admin.dashboard.charts.ranges.14'),
                'month' => __('admin.dashboard.charts.ranges.month'),
                '90' => __('admin.dashboard.charts.ranges.90'),
            ],
            'chartTypes' => [
                'line' => __('admin.dashboard.charts.types.line'),
                'bar' => __('admin.dashboard.charts.types.bar'),
            ],
            'recentOrders' => $data['recentOrders'],
            'attentionItems' => $data['attention'],
        ])->layout('layouts.admin', [
            'title' => __('admin.nav.dashboard'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

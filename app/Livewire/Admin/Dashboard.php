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

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function dismissGettingStarted(GettingStartedChecklist $checklist): void
    {
        $this->authorize('dashboard.view');
        $checklist->dismiss();
    }

    public function render(AdminRegistrar $admin, GettingStartedChecklist $gettingStarted, DashboardMetrics $metrics)
    {
        $data = $metrics->build();

        return view('livewire.admin.dashboard', [
            'gettingStarted' => $gettingStarted->items(),
            'metrics' => $data['metrics'],
            'revenueSeries' => $data['revenueSeries'],
            'orderSeries' => $data['orderSeries'],
            'recentOrders' => $data['recentOrders'],
            'attentionItems' => $data['attention'],
        ])->layout('layouts.admin', [
            'title' => __('admin.nav.dashboard'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

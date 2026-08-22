<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Operations\CronStatistics as CronStatisticsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class CronStatistics extends Component
{
    use AuthorizesRequests;

    public string $range = 'week';

    public function mount(): void
    {
        $this->authorize('settings.view');
    }

    public function render(AdminRegistrar $admin, CronStatisticsService $statistics)
    {
        return view('livewire.admin.system.cron-statistics', $statistics->viewData($this->range))
            ->layout('layouts.admin', [
                'title' => __('admin.cron_statistics.title'),
                'navigation' => $admin->navigationItems(),
            ]);
    }
}

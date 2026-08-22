<?php

declare(strict_types=1);

use App\Agovena\Operations\CronStatisticsRecorder;
use App\Agovena\Operations\SchedulerHealth;
use App\Livewire\Admin\System\CronStatistics;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    Cache::forget(SchedulerHealth::HEARTBEAT_KEY);
    Cache::forget(CronStatisticsRecorder::GLOBAL_LAST_RUN_KEY);
    foreach (CronStatisticsRecorder::TASKS as $task) {
        Cache::forget("agovena:cron:last-run:{$task}");
    }
});

test('cron statistics page shows scheduler status and setup command', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.cron-statistics'))
        ->assertOk()
        ->assertSee(__('admin.cron_statistics.title'), false)
        ->assertSee(__('admin.cron_statistics.never'), false)
        ->assertSee('php artisan schedule:run', false)
        ->assertSee(__('admin.cron_statistics.metrics.subscription_renewals'), false);
});

test('scheduled commands record cron statistics', function () {
    $recorder = app(CronStatisticsRecorder::class);

    $recorder->recordRun('prune-logs', ['logs_pruned' => 12]);
    $recorder->recordRun('cancel-unpaid-orders', ['unpaid_orders_cancelled' => 2]);

    expect($recorder->lastCronRun())->not->toBeNull()
        ->and($recorder->dailyCount('logs_pruned', now()))->toBe(12)
        ->and($recorder->dailyCount('unpaid_orders_cancelled', now()))->toBe(2);
});

test('cron statistics livewire reflects recorded metrics', function () {
    app(CronStatisticsRecorder::class)->recordRun('subscription-renewals', [
        'subscription_renewals' => 3,
    ]);
    Cache::put(SchedulerHealth::HEARTBEAT_KEY, now()->toIso8601String(), now()->addHours(2));

    Livewire::actingAs($this->createStaff())
        ->test(CronStatistics::class)
        ->assertOk()
        ->assertSee('3', false)
        ->assertSee(__('admin.cron_statistics.metrics.subscription_renewals'), false);
});

test('cron statistics range can switch to month view', function () {
    Livewire::actingAs($this->createStaff())
        ->test(CronStatistics::class)
        ->set('range', 'month')
        ->assertSet('range', 'month')
        ->assertSee(__('admin.cron_statistics.range_month'), false);
});

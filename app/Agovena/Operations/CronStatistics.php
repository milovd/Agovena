<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Settings\SettingsRepository;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CronStatistics
{
    public function __construct(
        private readonly CronStatisticsRecorder $recorder,
        private readonly SchedulerHealth $scheduler,
        private readonly ModuleManager $modules,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(string $range = 'week'): array
    {
        $days = $range === 'month' ? 30 : 7;
        $today = now()->startOfDay();

        return [
            'range' => $range,
            'statusCards' => $this->statusCards(),
            'chart' => $this->chartSeries($days),
            'metricCards' => $this->metricCards($today),
            'tasks' => $this->scheduledTasks(),
            'cronCommand' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'schedulerRequired' => $this->scheduler->isRequired(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, value: string, tone: string, hint: string|null}>
     */
    private function statusCards(): array
    {
        $lastScheduler = $this->scheduler->lastHeartbeat();
        $lastCron = $this->recorder->lastCronRun();
        $nextRun = $this->nextScheduledRun();

        return [
            [
                'key' => 'last_scheduler_run',
                'label' => __('admin.cron_statistics.last_scheduler_run'),
                'value' => $this->formatRunTime($lastScheduler),
                'tone' => $this->schedulerTone($lastScheduler),
                'hint' => $this->schedulerHint($lastScheduler),
            ],
            [
                'key' => 'last_cron_run',
                'label' => __('admin.cron_statistics.last_cron_run'),
                'value' => $this->formatRunTime($lastCron),
                'tone' => $this->cronTone($lastCron),
                'hint' => $lastCron ? $lastCron->timezone(config('app.timezone'))->isoFormat('LLL') : null,
            ],
            [
                'key' => 'next_cron_run',
                'label' => __('admin.cron_statistics.next_cron_run'),
                'value' => $nextRun ? $nextRun->diffForHumans() : __('admin.cron_statistics.next_unknown'),
                'tone' => 'default',
                'hint' => $nextRun?->timezone(config('app.timezone'))->isoFormat('LLL'),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartSeries(int $days): array
    {
        $labels = [];
        $series = [];
        foreach (CronStatisticsRecorder::METRICS as $metric) {
            $series[$metric] = [];
        }

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()->startOfDay()->subDays($offset);
            $labels[] = $date->isoFormat('MMM D');
            foreach (CronStatisticsRecorder::METRICS as $metric) {
                $series[$metric][] = $this->recorder->dailyCount($metric, $date);
            }
        }

        $colors = [
            'subscription_renewals' => 'var(--ag-color-chart-1)',
            'provisioning_synced' => 'var(--ag-color-chart-2)',
            'unpaid_orders_cancelled' => 'var(--ag-color-chart-4)',
            'logs_pruned' => 'var(--ag-color-chart-3)',
        ];

        $datasets = [];
        foreach (CronStatisticsRecorder::METRICS as $metric) {
            $datasets[] = [
                'label' => __("admin.cron_statistics.metrics.{$metric}"),
                'data' => $series[$metric],
                'borderColor' => $colors[$metric],
                'backgroundColor' => $colors[$metric],
                'tension' => 0.35,
                'fill' => false,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @return list<array{id: string, label: string, value: int, description: string}>
     */
    private function metricCards(Carbon $today): array
    {
        $dateLabel = $today->isoFormat('LL');

        return [
            [
                'id' => 'subscription_renewals',
                'label' => __('admin.cron_statistics.metrics.subscription_renewals'),
                'value' => $this->recorder->dailyCount('subscription_renewals', $today),
                'description' => __('admin.cron_statistics.metric_descriptions.subscription_renewals', ['date' => $dateLabel]),
            ],
            [
                'id' => 'provisioning_synced',
                'label' => __('admin.cron_statistics.metrics.provisioning_synced'),
                'value' => $this->recorder->dailyCount('provisioning_synced', $today),
                'description' => __('admin.cron_statistics.metric_descriptions.provisioning_synced', ['date' => $dateLabel]),
            ],
            [
                'id' => 'unpaid_orders_cancelled',
                'label' => __('admin.cron_statistics.metrics.unpaid_orders_cancelled'),
                'value' => $this->recorder->dailyCount('unpaid_orders_cancelled', $today),
                'description' => __('admin.cron_statistics.metric_descriptions.unpaid_orders_cancelled', ['date' => $dateLabel]),
            ],
            [
                'id' => 'logs_pruned',
                'label' => __('admin.cron_statistics.metrics.logs_pruned'),
                'value' => $this->recorder->dailyCount('logs_pruned', $today),
                'description' => __('admin.cron_statistics.metric_descriptions.logs_pruned', ['date' => $dateLabel]),
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, frequency: string, last_run: string|null, active: bool}>
     */
    private function scheduledTasks(): array
    {
        return $this->scheduleEvents()
            ->filter(fn (Event $event): bool => filled($event->command))
            ->map(function (Event $event): array {
                $id = $this->taskId($event);

                return [
                    'id' => $id,
                    'label' => __("admin.cron_statistics.tasks.{$id}"),
                    'frequency' => $this->frequencyLabel($event),
                    'last_run' => $this->formatRunTime($this->recorder->lastRun($id)),
                    'active' => $this->taskIsActive($id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Event>
     */
    private function scheduleEvents(): Collection
    {
        return collect(app(Schedule::class)->events());
    }

    private function nextScheduledRun(): ?Carbon
    {
        $dates = $this->scheduleEvents()
            ->map(fn (Event $event): Carbon => $event->nextRunDate())
            ->values();

        if ($dates->isEmpty()) {
            return null;
        }

        /** @var Carbon $next */
        $next = $dates->sort()->first();

        return $next;
    }

    private function taskId(Event $event): string
    {
        $command = (string) ($event->command ?? '');

        return match (true) {
            str_contains($command, 'agovena:process-subscription-renewals') => 'subscription-renewals',
            str_contains($command, 'agovena:sync-provisioning') => 'sync-provisioning',
            str_contains($command, 'agovena:cancel-stale-unpaid-orders') => 'cancel-unpaid-orders',
            str_contains($command, 'agovena:prune-logs') => 'prune-logs',
            default => str($event->description ?: $command)->slug()->toString(),
        };
    }

    private function frequencyLabel(Event $event): string
    {
        $expression = trim((string) $event->getExpression());
        if ($expression === '* * * * *') {
            return __('admin.cron_statistics.frequencies.every_minute');
        }
        if ($expression === '0 * * * *') {
            return __('admin.cron_statistics.frequencies.hourly');
        }
        if (preg_match('/^0 0 \* \* \*$/', $expression) === 1) {
            return __('admin.cron_statistics.frequencies.daily');
        }

        return $expression;
    }

    private function taskIsActive(string $taskId): bool
    {
        return match ($taskId) {
            'subscription-renewals' => $this->modules->isEnabled('subscriptions'),
            'sync-provisioning' => $this->modules->isEnabled('provisioning'),
            'cancel-unpaid-orders' => (int) $this->settings->get('store', 'unpaid_order_cancel_after_days', 0) > 0,
            'prune-logs' => true,
            default => true,
        };
    }

    private function formatRunTime(?Carbon $time): string
    {
        if ($time === null) {
            return __('admin.cron_statistics.never');
        }

        return $time->diffForHumans();
    }

    private function schedulerTone(?Carbon $last): string
    {
        if ($this->scheduler->isStale()) {
            return 'danger';
        }
        if ($last === null) {
            return 'muted';
        }

        return 'ok';
    }

    private function cronTone(?Carbon $last): string
    {
        if ($last === null) {
            return $this->scheduler->isRequired() ? 'danger' : 'muted';
        }
        if ($this->scheduler->isRequired() && $last->lt(now()->subMinutes(15))) {
            return 'danger';
        }

        return 'ok';
    }

    private function schedulerHint(?Carbon $last): string
    {
        if ($this->scheduler->isStale()) {
            return __('admin.updates.scheduler_stale', [
                'time' => $last?->timezone(config('app.timezone'))->isoFormat('LLL') ?? __('admin.updates.scheduler_never'),
            ]);
        }
        if ($last === null) {
            return __('admin.updates.scheduler_idle');
        }

        return $last->timezone(config('app.timezone'))->isoFormat('LLL');
    }
}

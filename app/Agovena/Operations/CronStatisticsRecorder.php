<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class CronStatisticsRecorder
{
    public const GLOBAL_LAST_RUN_KEY = 'agovena:cron:last-run';

    /** @var list<string> */
    public const TASKS = [
        'subscription-renewals',
        'sync-provisioning',
        'cancel-unpaid-orders',
        'prune-logs',
        'backup',
    ];

    /** @var list<string> */
    public const METRICS = [
        'subscription_renewals',
        'provisioning_synced',
        'unpaid_orders_cancelled',
        'logs_pruned',
        'backups_created',
        'backups_pruned',
    ];

    /**
     * @param  array<string, int>  $counts
     */
    public function recordRun(string $task, array $counts = []): void
    {
        $now = now()->toIso8601String();
        Cache::put("agovena:cron:last-run:{$task}", $now, now()->addDays(90));
        Cache::put(self::GLOBAL_LAST_RUN_KEY, $now, now()->addDays(90));

        $date = now()->toDateString();
        foreach ($counts as $metric => $increment) {
            $amount = max(0, $increment);
            if ($amount === 0) {
                continue;
            }
            $key = $this->dailyKey($metric, $date);
            Cache::put($key, (int) Cache::get($key, 0) + $amount, now()->addDays(45));
        }
    }

    public function lastRun(string $task): ?Carbon
    {
        return $this->parseTimestamp(Cache::get("agovena:cron:last-run:{$task}"));
    }

    public function lastCronRun(): ?Carbon
    {
        return $this->parseTimestamp(Cache::get(self::GLOBAL_LAST_RUN_KEY));
    }

    public function dailyCount(string $metric, Carbon $date): int
    {
        return (int) Cache::get($this->dailyKey($metric, $date->toDateString()), 0);
    }

    private function dailyKey(string $metric, string $date): string
    {
        return "agovena:cron:daily:{$metric}:{$date}";
    }

    private function parseTimestamp(mixed $raw): ?Carbon
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}

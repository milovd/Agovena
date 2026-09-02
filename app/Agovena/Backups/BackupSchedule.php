<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Console\Scheduling\Schedule;

final class BackupSchedule
{
    public const SETTING_GROUP = 'backups';

    public const SETTING_KEY = 'interval';

    public const DEFAULT_INTERVAL = 'daily';

    public const INTERVALS = [
        'disabled',
        'hourly',
        'every_6_hours',
        'every_12_hours',
        'daily',
        'weekly',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function interval(): string
    {
        try {
            $configuredInterval = $this->settings->get(
                self::SETTING_GROUP,
                self::SETTING_KEY,
                config('agovena.backups.interval', self::DEFAULT_INTERVAL),
            );
        } catch (\Throwable) {
            $configuredInterval = config('agovena.backups.interval', self::DEFAULT_INTERVAL);
        }

        return in_array($configuredInterval, self::INTERVALS, true)
            ? $configuredInterval
            : self::DEFAULT_INTERVAL;
    }

    public function isEnabled(): bool
    {
        return $this->interval() !== 'disabled';
    }

    public function register(Schedule $schedule): void
    {
        $expression = match ($this->interval()) {
            'hourly' => '0 * * * *',
            'every_6_hours' => '0 */6 * * *',
            'every_12_hours' => '0 */12 * * *',
            'daily' => '30 2 * * *',
            'weekly' => '30 2 * * 0',
            default => null,
        };

        if ($expression === null) {
            return;
        }

        $schedule->command('agovena:backup')
            ->cron($expression)
            ->name('agovena-backup')
            ->withoutOverlapping(120);
    }
}

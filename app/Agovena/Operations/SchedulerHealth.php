<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SchedulerHealth
{
    public const HEARTBEAT_KEY = 'agovena:scheduler:heartbeat';

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly SettingsRepository $settings,
    ) {}

    public function lastHeartbeat(): ?Carbon
    {
        $raw = Cache::get(self::HEARTBEAT_KEY);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isRequired(): bool
    {
        if ($this->modules->isEnabled('subscriptions')) {
            return true;
        }

        return (int) $this->settings->get('store', 'unpaid_order_cancel_after_days', 0) > 0;
    }

    public function isStale(): bool
    {
        if (! $this->isRequired()) {
            return false;
        }

        $last = $this->lastHeartbeat();
        if ($last === null) {
            return true;
        }

        return $last->lt(now()->subMinutes(10));
    }

    /**
     * @return array{required: bool, stale: bool, last: string|null}
     */
    public function snapshot(): array
    {
        $last = $this->lastHeartbeat();

        return [
            'required' => $this->isRequired(),
            'stale' => $this->isStale(),
            'last' => $last?->toIso8601String(),
        ];
    }
}

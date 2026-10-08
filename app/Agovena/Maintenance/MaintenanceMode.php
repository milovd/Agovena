<?php

declare(strict_types=1);

namespace App\Agovena\Maintenance;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Storefront maintenance switch. The state lives in the settings table so it
 * survives cache clears. Storefront requests read one cache entry, which also
 * caches "never configured", so an untouched shop costs no database query.
 * Reads never throw: an unreadable setting counts as off.
 */
final class MaintenanceMode
{
    public const SETTING_GROUP = 'maintenance';

    public const SETTING_KEY = 'state';

    public const MESSAGE_MAX_LENGTH = 500;

    public const DEFAULT_RETRY_AFTER_SECONDS = 600;

    public const PERMISSION = 'maintenance.manage';

    private const CACHE_KEY = 'agovena.maintenance.state';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function state(): MaintenanceState
    {
        try {
            $encoded = Cache::rememberForever(self::CACHE_KEY, fn (): string => $this->storedJson());
        } catch (\Throwable) {
            // Cache store unavailable: fall back to the database, then to "off".
            try {
                $encoded = $this->storedJson();
            } catch (\Throwable) {
                return MaintenanceState::off();
            }
        }

        if (! is_string($encoded)) {
            return MaintenanceState::off();
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? MaintenanceState::fromArray($decoded) : MaintenanceState::off();
    }

    public function enabled(): bool
    {
        return $this->state()->enabled;
    }

    /**
     * Turn maintenance on, or update the message and end time while it is on.
     */
    public function enable(?string $message, ?CarbonInterface $endsAt): MaintenanceState
    {
        $message = self::normaliseMessage($message);
        if ($message !== null && mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            throw new \InvalidArgumentException('The maintenance message is too long.');
        }

        $before = $this->state();
        $after = new MaintenanceState(
            enabled: true,
            message: $message,
            endsAt: $endsAt === null ? null : CarbonImmutable::instance($endsAt)->utc(),
            enabledAt: $before->enabled && $before->enabledAt !== null ? $before->enabledAt : CarbonImmutable::now()->utc(),
        );

        $this->store($after);
        $this->audit->log(
            action: $before->enabled ? 'maintenance.updated' : 'maintenance.enabled',
            before: $this->auditSnapshot($before),
            after: $this->auditSnapshot($after),
            severity: $before->enabled ? 'info' : 'warning',
            category: 'admin',
        );

        return $after;
    }

    public function disable(): MaintenanceState
    {
        $before = $this->state();
        $after = new MaintenanceState(false, $before->message, $before->endsAt);

        $this->store($after);
        if ($before->enabled) {
            $this->audit->log(
                action: 'maintenance.disabled',
                before: $this->auditSnapshot($before),
                after: $this->auditSnapshot($after),
                category: 'admin',
            );
        }

        return $after;
    }

    /**
     * Drop the cached copy so the next read comes from the database.
     */
    public function forgetCached(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
            $this->settings->forget(self::SETTING_GROUP, self::SETTING_KEY);
        } catch (\Throwable) {
            // A missing cache store must not stop the recovery path.
        }
    }

    public function retryAfterSeconds(MaintenanceState $state): int
    {
        return $state->retryAfterSeconds(CarbonImmutable::now(), self::DEFAULT_RETRY_AFTER_SECONDS);
    }

    /**
     * Data for the storefront banner shown to staff who bypass maintenance.
     *
     * @return array{manageUrl: string|null}|null
     */
    public function staffNotice(mixed $user): ?array
    {
        try {
            if (! $user instanceof User || ! $user->canAccessAdmin() || ! $this->enabled()) {
                return null;
            }

            $canManage = $user->can(self::PERMISSION) && Route::has('admin.maintenance');

            return ['manageUrl' => $canManage ? route('admin.maintenance') : null];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normaliseMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = (string) preg_replace('/[^\P{C}\n]/u', '', $message);
        $message = trim($message);

        return $message === '' ? null : $message;
    }

    private function store(MaintenanceState $state): void
    {
        $this->settings->set(self::SETTING_GROUP, self::SETTING_KEY, $state->toArray());
        $this->forgetCached();
    }

    /**
     * The stored state as JSON, or an empty object when it was never set.
     */
    private function storedJson(): string
    {
        $value = Setting::query()
            ->where('group', self::SETTING_GROUP)
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return is_string($value) && $value !== '' ? $value : '{}';
    }

    /**
     * @return array{enabled: bool, message: string|null, ends_at: string|null}
     */
    private function auditSnapshot(MaintenanceState $state): array
    {
        return [
            'enabled' => $state->enabled,
            'message' => $state->message,
            'ends_at' => $state->endsAt?->toIso8601String(),
        ];
    }
}

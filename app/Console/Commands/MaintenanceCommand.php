<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Maintenance\MaintenanceMode;
use App\Agovena\Maintenance\MaintenanceState;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Recovery route for storefront maintenance. Works without the Admin: it
 * reads and writes the stored setting directly and drops the cached copy, so
 * a stale cache never hides the real state.
 */
final class MaintenanceCommand extends Command
{
    protected $signature = 'agovena:maintenance
        {action : on, off or status}
        {--message= : Public plain-text message for the maintenance page (on)}
        {--until= : Expected end time, for example "2026-10-08 18:00" in the app timezone (on)}';

    protected $description = 'Turn storefront maintenance on or off, or show its status. The Admin, staff sign-in and webhooks stay reachable.';

    public function handle(MaintenanceMode $maintenance): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        if (! in_array($action, ['on', 'off', 'status'], true)) {
            $this->error('Unknown action. Use on, off or status.');

            return self::FAILURE;
        }

        $maintenance->forgetCached();

        try {
            $state = match ($action) {
                'on' => $this->enable($maintenance),
                'off' => $maintenance->disable(),
                default => $maintenance->state(),
            };
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('The maintenance setting could not be read or saved. Check the database connection.');

            return self::FAILURE;
        } finally {
            $maintenance->forgetCached();
        }

        $this->report($state);

        return self::SUCCESS;
    }

    private function enable(MaintenanceMode $maintenance): MaintenanceState
    {
        $current = $maintenance->state();

        $message = $this->option('message');
        $message = is_string($message) ? $message : $current->message;
        $normalised = MaintenanceMode::normaliseMessage($message);
        if ($normalised !== null && mb_strlen($normalised) > MaintenanceMode::MESSAGE_MAX_LENGTH) {
            throw new \InvalidArgumentException('The message may not be longer than '.MaintenanceMode::MESSAGE_MAX_LENGTH.' characters.');
        }

        $endsAt = $current->endsAt !== null && $current->endsAt->isFuture() ? $current->endsAt : null;
        $until = $this->option('until');
        if (is_string($until) && trim($until) !== '') {
            try {
                $endsAt = CarbonImmutable::parse($until, (string) config('app.timezone', 'UTC'));
            } catch (\Throwable) {
                throw new \InvalidArgumentException('The --until value is not a valid date and time.');
            }
            if ($endsAt->isPast()) {
                throw new \InvalidArgumentException('The --until value must be in the future.');
            }
        }

        return $maintenance->enable($normalised, $endsAt);
    }

    private function report(MaintenanceState $state): void
    {
        $this->line('Storefront maintenance: '.($state->enabled ? 'on' : 'off'));

        if (! $state->enabled) {
            return;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $this->line('Message: '.($state->message ?? '(default)'));
        $this->line('Expected end: '.($state->endsAt?->timezone($timezone)->format('Y-m-d H:i T') ?? '(not set)'));
        $this->line('The Admin, staff sign-in and payment webhooks stay reachable. Run "php artisan agovena:maintenance off" to reopen the storefront.');
    }
}

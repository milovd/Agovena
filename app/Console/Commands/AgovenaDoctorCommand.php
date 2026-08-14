<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\RequirementCheck;
use App\Agovena\Operations\SchedulerHealth;
use Illuminate\Console\Command;

final class AgovenaDoctorCommand extends Command
{
    protected $signature = 'agovena:doctor';

    protected $description = 'Check Agovena runtime requirements and installation readiness';

    public function handle(InstallationRequirements $requirements, InstallationState $state, SchedulerHealth $scheduler): int
    {
        $this->info('Agovena doctor');
        $this->newLine();

        $failed = 0;
        $warnings = 0;

        foreach ([...$requirements->checks(), ...$this->platformChecks($scheduler)] as $check) {
            $this->line($this->formatCheck($check));

            if (! $check->passed && $check->required) {
                $failed++;
            } elseif (! $check->passed) {
                $warnings++;
            }
        }

        $this->newLine();
        $this->line('Installation: '.($state->installed()
            ? '<info>installed</info> ('.$state->installedAt().')'
            : '<comment>not installed</comment>'));

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} required check(s) failed.".($warnings > 0 ? " {$warnings} warning(s)." : ''));

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn("All required checks passed with {$warnings} warning(s).");

            return self::SUCCESS;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function formatCheck(RequirementCheck $check): string
    {
        $status = $check->passed ? '<info>PASS</info>' : ($check->required ? '<error>FAIL</error>' : '<comment>WARN</comment>');
        $label = __($check->label);
        $detail = $check->technicalDetail ?? $check->detail;
        $suffix = $detail !== null ? " — {$detail}" : '';

        return "{$status}  {$label}{$suffix}";
    }

    /** @return list<RequirementCheck> */
    private function platformChecks(SchedulerHealth $scheduler): array
    {
        $queue = (string) config('queue.default', '');
        $mail = (string) config('mail.default', '');
        $env = (string) config('app.env');
        $debugOff = $env !== 'production' || ! (bool) config('app.debug');
        $last = $scheduler->lastHeartbeat();

        return [
            new RequirementCheck(
                id: 'production_debug',
                label: 'installer.checks.production_debug',
                passed: $debugOff,
                required: $env === 'production',
                detail: 'APP_ENV='.$env.' APP_DEBUG='.(config('app.debug') ? 'true' : 'false'),
            ),
            new RequirementCheck(
                id: 'queue_connection',
                label: 'installer.checks.queue_connection',
                passed: $queue !== '' && $queue !== 'sync',
                required: false,
                detail: $queue,
            ),
            new RequirementCheck(
                id: 'mail_default',
                label: 'installer.checks.mail_default',
                passed: $mail !== '' && $mail !== 'log',
                required: false,
                detail: $mail,
            ),
            new RequirementCheck(
                id: 'scheduler',
                label: 'installer.checks.scheduler',
                passed: ! $scheduler->isStale(),
                required: false,
                detail: $last?->toIso8601String() ?? 'none',
            ),
            new RequirementCheck(
                id: 'https',
                label: 'installer.checks.https',
                passed: $env !== 'production' || str_starts_with((string) config('app.url'), 'https://'),
                required: false,
                detail: (string) config('app.url'),
            ),
        ];
    }
}

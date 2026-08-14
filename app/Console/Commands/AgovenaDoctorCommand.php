<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\RequirementCheck;
use App\Agovena\Operations\SchedulerHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        foreach ([...$requirements->checks(), ...$this->platformChecks($scheduler, $state)] as $check) {
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
    private function platformChecks(SchedulerHealth $scheduler, InstallationState $state): array
    {
        $queue = (string) config('queue.default', '');
        $mail = (string) config('mail.default', '');
        $env = (string) config('app.env');
        $debugOff = $env !== 'production' || ! (bool) config('app.debug');
        $last = $scheduler->lastHeartbeat();
        $marker = $state->markerFile();
        $dbInstalled = $state->installed();
        $dbId = $state->installId();
        $markerOnly = $marker !== null && ! $dbInstalled;
        $dbWithoutMarker = $dbInstalled && $marker === null;
        $idMismatch = $marker !== null && $dbId !== null && $marker['install_id'] !== $dbId;

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
                // Fail (not only warn) when subscriptions/provisioning/unpaid-cancel need cron.
                required: $scheduler->isRequired(),
                detail: $last?->toIso8601String() ?? 'none',
            ),
            new RequirementCheck(
                id: 'https',
                label: 'installer.checks.https',
                passed: $env !== 'production' || str_starts_with((string) config('app.url'), 'https://'),
                required: false,
                detail: (string) config('app.url'),
            ),
            new RequirementCheck(
                id: 'install_restore',
                label: 'installer.checks.install_restore',
                passed: ! $markerOnly && ! $dbWithoutMarker && ! $idMismatch,
                required: false,
                detail: $this->restoreDetail($markerOnly, $dbWithoutMarker, $idMismatch),
            ),
            ...$this->runtimeHealthChecks($env),
        ];
    }

    /** @return list<RequirementCheck> */
    private function runtimeHealthChecks(string $env): array
    {
        $logs = storage_path('logs');
        $logsWritable = is_dir($logs) ? is_writable($logs) : is_writable(storage_path());
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $hasAssets = is_file(public_path('build/manifest.json'))
            || is_file(public_path('build/.vite/manifest.json'));
        $privateServe = (bool) config('filesystems.disks.local.serve', false);
        $storage = str_replace('\\', '/', storage_path());
        $ephemeral = str_contains($storage, '/tmp/')
            || str_ends_with($storage, '/tmp')
            || str_contains(strtolower($storage), '/temp/');

        return [
            new RequirementCheck(
                id: 'logs_writable',
                label: 'installer.checks.logs_writable',
                passed: $logsWritable,
                required: true,
                detail: $logs,
            ),
            new RequirementCheck(
                id: 'failed_jobs',
                label: 'installer.checks.failed_jobs',
                passed: $failedJobs === 0,
                required: false,
                detail: (string) $failedJobs,
            ),
            new RequirementCheck(
                id: 'frontend_assets',
                label: 'installer.checks.frontend_assets',
                passed: $env !== 'production' || $hasAssets,
                required: false,
                detail: $hasAssets ? 'public/build/manifest.json' : 'missing public/build (run npm run build, or ship a release artifact)',
            ),
            new RequirementCheck(
                id: 'private_disk',
                label: 'installer.checks.private_disk',
                passed: ! $privateServe,
                required: $env === 'production',
                detail: 'filesystems.disks.local.serve='.($privateServe ? 'true' : 'false'),
            ),
            new RequirementCheck(
                id: 'storage_persistent',
                label: 'installer.checks.storage_persistent',
                passed: ! $ephemeral,
                required: false,
                detail: $storage,
            ),
        ];
    }

    private function restoreDetail(bool $markerOnly, bool $dbWithoutMarker, bool $idMismatch): string
    {
        if ($markerOnly) {
            return 'storage marker exists without a database install lock';
        }
        if ($dbWithoutMarker) {
            return 'database is installed but storage/app/agovena/installed.json is missing';
        }
        if ($idMismatch) {
            return 'database install id does not match the storage marker';
        }

        return 'database lock and storage marker agree';
    }
}

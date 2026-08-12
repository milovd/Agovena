<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\RequirementCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class AgovenaDoctorCommand extends Command
{
    protected $signature = 'agovena:doctor';

    protected $description = 'Check Agovena runtime requirements and installation readiness';

    public function handle(InstallationRequirements $requirements, InstallationState $state): int
    {
        $this->info('Agovena doctor');
        $this->newLine();

        $failed = 0;
        $warnings = 0;

        foreach ([...$requirements->checks(), ...$this->platformChecks()] as $check) {
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
    private function platformChecks(): array
    {
        $queue = (string) config('queue.default', '');
        $mail = (string) config('mail.default', '');

        return [
            new RequirementCheck(
                id: 'extensions_table',
                label: 'installer.checks.extensions_table',
                passed: Schema::hasTable('agovena_extensions'),
            ),
            new RequirementCheck(
                id: 'tax_rates_table',
                label: 'installer.checks.tax_rates_table',
                passed: Schema::hasTable('tax_rates'),
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
        ];
    }
}

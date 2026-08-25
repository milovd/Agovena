<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Operations\ProviderHealthSummary;
use Illuminate\Console\Command;

final class AgovenaVerifyProvidersCommand extends Command
{
    protected $signature = 'agovena:verify-providers
                            {extension? : Optional Extension id (e.g. mollie)}
                            {--sandbox : Refuse live_ credentials; connection check only}';

    protected $description = 'Run enabled Extension health checks without creating payments, shipments, or servers';

    public function handle(ProviderHealthSummary $summary, ExtensionManager $extensions, ExtensionSettingsRepository $settings): int
    {
        $filter = $this->argument('extension');
        $filter = is_string($filter) && $filter !== '' ? $filter : null;
        $sandbox = (bool) $this->option('sandbox');

        $this->info('Agovena provider connection checks');
        $this->comment('This only tests connectivity and credentials. It does not place live charges or create remote resources.');
        if ($sandbox) {
            $this->comment('Sandbox mode: live_ API keys are refused. Transactional sandbox verification remains a separate operator checklist.');
        }
        $this->newLine();

        if ($filter !== null && ! $extensions->isEnabled($filter)) {
            $this->error("Extension [{$filter}] is not enabled (or not installed).");

            return self::FAILURE;
        }

        if ($sandbox && ($filter === null || $filter === 'mollie')) {
            $blocked = $this->refuseLiveMollieKey($settings, $filter === 'mollie' || $extensions->isEnabled('mollie'));
            if ($blocked !== null) {
                $this->error($blocked);

                return self::FAILURE;
            }
        }

        $rows = $summary->rows();
        if ($filter !== null) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] === $filter));
        }

        if ($rows === []) {
            $this->warn($filter !== null
                ? "No health check exposed for Extension [{$filter}]."
                : 'No enabled Extensions expose a health check.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($rows as $row) {
            $status = $row['ok'] ? '<info>OK</info>' : '<error>FAIL</error>';
            $this->line("{$status} {$row['id']} ({$row['category']}) - {$row['message']}");
            if (! $row['ok']) {
                $failed++;
            }
        }

        $this->newLine();
        if ($failed > 0) {
            $this->error("{$failed} provider check(s) failed. Live/sandbox transaction verification was not performed.");

            return self::FAILURE;
        }

        $this->info('Connection checks passed. Status remains MOCK-TESTED until the sandbox checklist in deploy/LIVE_PROVIDER_CHECKS.md is completed.');
        $this->comment('This command never creates payments. Do not treat OK as SANDBOX-VERIFIED or PRODUCTION-VERIFIED.');

        return self::SUCCESS;
    }

    private function refuseLiveMollieKey(ExtensionSettingsRepository $settings, bool $checkMollie): ?string
    {
        if (! $checkMollie) {
            return null;
        }

        $env = getenv('AGOVENA_EXT_MOLLIE_API_KEY');
        $key = is_string($env) && $env !== '' ? $env : $settings->get('mollie', 'api_key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        if (str_starts_with($key, 'live_')) {
            return 'Sandbox mode refuses Mollie live_ API keys. Use a test_ key via Admin → Extensions → Mollie (api_key) or AGOVENA_EXT_MOLLIE_API_KEY.';
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Operations\ProviderHealthSummary;
use Illuminate\Console\Command;

final class AgovenaVerifyProvidersCommand extends Command
{
    protected $signature = 'agovena:verify-providers';

    protected $description = 'Run enabled Extension health checks without creating payments, shipments, or servers';

    public function handle(ProviderHealthSummary $summary): int
    {
        $this->info('Agovena provider connection checks');
        $this->comment('This only tests connectivity and credentials. It does not place live charges or create remote resources.');
        $this->newLine();

        $rows = $summary->rows();
        if ($rows === []) {
            $this->warn('No enabled Extensions expose a health check.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($rows as $row) {
            $status = $row['ok'] ? '<info>OK</info>' : '<error>FAIL</error>';
            $this->line("{$status}  {$row['id']} ({$row['category']}) — {$row['message']}");
            if (! $row['ok']) {
                $failed++;
            }
        }

        $this->newLine();
        if ($failed > 0) {
            $this->error("{$failed} provider check(s) failed. Live provider verification was not performed.");

            return self::FAILURE;
        }

        $this->info('Connection checks passed. This is not live transaction verification.');

        return self::SUCCESS;
    }
}

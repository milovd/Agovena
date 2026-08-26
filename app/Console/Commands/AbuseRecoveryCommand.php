<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Abuse\SecurityAbuseService;
use Illuminate\Console\Command;

final class AbuseRecoveryCommand extends Command
{
    protected $signature = 'agovena:abuse-recover {ip : IP address to recover}';

    protected $description = 'Remove an IP block and add an explicit allow rule.';

    public function handle(SecurityAbuseService $abuse): int
    {
        $ip = (string) $this->argument('ip');
        try {
            $abuse->recoverIp($ip);
        } catch (\InvalidArgumentException) {
            $this->error('The IP address is invalid.');

            return self::FAILURE;
        }

        $this->info('Abuse recovery rule applied.');

        return self::SUCCESS;
    }
}

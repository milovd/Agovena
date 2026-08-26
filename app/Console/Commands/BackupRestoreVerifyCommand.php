<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Backups\BackupRestoreVerifier;
use Illuminate\Console\Command;

final class BackupRestoreVerifyCommand extends Command
{
    protected $signature = 'agovena:backup-verify {path : Relative encrypted backup artifact path}';

    protected $description = 'Verify that an encrypted backup artifact is readable and has a supported database payload';

    public function handle(BackupRestoreVerifier $verifier): int
    {
        $result = $verifier->verify((string) $this->argument('path'));
        if (! $result->valid) {
            $this->error('Backup verification failed: '.($result->errorCode ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info('Backup artifact verified.');

        return self::SUCCESS;
    }
}

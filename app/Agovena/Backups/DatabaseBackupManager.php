<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

interface DatabaseBackupManager
{
    public function backupConfiguredDatabase(): BackupRunResult;
}

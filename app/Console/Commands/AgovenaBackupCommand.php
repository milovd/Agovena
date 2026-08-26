<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Backups\BackupManager;
use App\Agovena\Operations\CronStatisticsRecorder;
use App\Notifications\BackupFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class AgovenaBackupCommand extends Command
{
    protected $signature = 'agovena:backup';

    protected $description = 'Create an encrypted database backup and prune expired artifacts';

    public function handle(BackupManager $backups, CronStatisticsRecorder $statistics): int
    {
        $result = $backups->backupConfiguredDatabase();

        if (! $result->success || $result->path === null) {
            Log::error('Agovena database backup failed.', [
                'error_code' => $result->errorCode ?? 'unknown',
            ]);
            $this->sendFailureAlert($result->errorCode ?? 'unknown');
            $this->error('Database backup failed. Check the operational logs for the error code.');

            return self::FAILURE;
        }

        $statistics->recordRun('backup', [
            'backups_created' => 1,
            'backups_pruned' => $result->prunedCount,
        ]);
        $this->info('Backup created: '.$result->path);
        if ($result->prunedCount > 0) {
            $this->info('Expired backups pruned: '.$result->prunedCount);
        }

        return self::SUCCESS;
    }

    private function sendFailureAlert(string $errorCode): void
    {
        $email = filter_var(config('agovena.backups.alert_email'), FILTER_VALIDATE_EMAIL);
        if (! is_string($email)) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new BackupFailedNotification($errorCode));
        } catch (\Throwable $exception) {
            Log::warning('Agovena backup failure alert could not be sent.', [
                'error_code' => $errorCode,
                'alert_error' => $exception::class,
            ]);
        }
    }
}

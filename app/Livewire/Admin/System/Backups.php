<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Backups\BackupActionResult;
use App\Agovena\Backups\BackupManager;
use App\Agovena\Backups\BackupSchedule;
use App\Agovena\Backups\DatabaseBackupManager;
use App\Agovena\Operations\SchedulerHealth;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Concerns\RequiresRecentPassword;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

final class Backups extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword {
        confirmRecentPassword as protected confirmRecentPasswordAfterAuthorization;
    }

    public string $lastResult = '';

    public string $backupInterval = BackupSchedule::DEFAULT_INTERVAL;

    public function mount(BackupSchedule $schedule): void
    {
        $this->authorize('backups.view');
        $this->backupInterval = $schedule->interval();
    }

    public function createBackup(DatabaseBackupManager $backupManager): void
    {
        $this->authorize('backups.manage');

        $result = $backupManager->backupConfiguredDatabase();
        if ($result->success) {
            $this->lastResult = 'success';
            session()->flash('status', __('admin.backups.created'));

            return;
        }

        $this->lastResult = 'error';
        session()->flash('error', __('admin.backups.failed', [
            'code' => $result->errorCode ?? 'unknown',
        ]));
    }

    public function saveSchedule(SettingsRepository $settings): void
    {
        $this->authorize('backups.manage');
        $this->validate([
            'backupInterval' => ['required', 'string', Rule::in(BackupSchedule::INTERVALS)],
        ]);

        $settings->set(BackupSchedule::SETTING_GROUP, BackupSchedule::SETTING_KEY, $this->backupInterval);
        session()->flash('status', __('admin.backups.schedule_saved'));
    }

    public function deleteBackup(string $path): void
    {
        $this->authorize('backups.manage');

        if (! $this->requireRecentPassword('completeDeleteBackup', ['path' => $path])) {
            return;
        }

        $this->completeDeleteBackup($path, app(BackupManager::class));
    }

    public function completeDeleteBackup(string $path, BackupManager $backupManager): void
    {
        $this->authorize('backups.manage');
        if (! $this->requireRecentPassword('completeDeleteBackup', ['path' => $path])) {
            return;
        }

        $this->reportAction(
            $backupManager->deleteBackup($path),
            __('admin.backups.deleted'),
        );
    }

    public function restoreBackup(string $path): void
    {
        $this->authorize('backups.manage');

        if (! $this->requireRecentPassword('completeRestoreBackup', ['path' => $path])) {
            return;
        }

        $this->completeRestoreBackup($path, app(BackupManager::class));
    }

    public function completeRestoreBackup(string $path, BackupManager $backupManager): void
    {
        $this->authorize('backups.manage');
        if (! $this->requireRecentPassword('completeRestoreBackup', ['path' => $path])) {
            return;
        }

        $this->reportAction(
            $backupManager->restoreBackup($path),
            __('admin.backups.restored'),
        );
    }

    public function confirmRecentPassword(): void
    {
        $this->authorize('backups.manage');
        $this->confirmRecentPasswordAfterAuthorization();
    }

    private function reportAction(BackupActionResult $result, string $successMessage): void
    {
        $this->lastResult = $result->success ? 'success' : 'error';
        session()->flash(
            $result->success ? 'status' : 'error',
            $result->success
                ? $successMessage
                : __('admin.backups.action_failed', ['code' => $result->errorCode ?? 'unknown']),
        );
    }

    /**
     * @return array<int, array{name: string, path: string, modifiedAt: int, size: int}>
     */
    private function backupFiles(): array
    {
        $diskName = (string) config('agovena.backups.disk', 'local');
        $directory = trim((string) config('agovena.backups.directory', 'backups'), '/');
        $prefix = $directory === '' ? '' : $directory.'/';

        try {
            $disk = Storage::disk($diskName);
            $files = [];

            foreach ($disk->files($directory) as $path) {
                if (! str_starts_with($path, $prefix.'database-')
                    || $path !== $prefix.basename($path)
                    || preg_match('/^database-(sqlite|mysql)-[A-Za-z0-9_-]+\.enc$/', basename($path)) !== 1
                ) {
                    continue;
                }

                try {
                    $files[] = [
                        'name' => basename($path),
                        'path' => $path,
                        'modifiedAt' => $disk->lastModified($path),
                        'size' => $disk->size($path),
                    ];
                } catch (Throwable) {
                    continue;
                }
            }
        } catch (Throwable) {
            return [];
        }

        usort($files, static fn (array $left, array $right): int => $right['modifiedAt'] <=> $left['modifiedAt']);

        return $files;
    }

    public function render(AdminRegistrar $admin, SchedulerHealth $scheduler)
    {
        $this->authorize('backups.view');

        $allFiles = $this->backupFiles();
        $files = array_slice($allFiles, 0, 100);
        $lastHeartbeat = $scheduler->lastHeartbeat();
        $schedulerHealthy = $lastHeartbeat !== null && $lastHeartbeat->gte(now()->subMinutes(10));

        return view('livewire.admin.system.backups', [
            'files' => $files,
            'availableCount' => count($allFiles),
            'databaseDriver' => (string) config('database.connections.'.config('database.default').'.driver', 'unknown'),
            'diskName' => (string) config('agovena.backups.disk', 'local'),
            'directory' => trim((string) config('agovena.backups.directory', 'backups'), '/'),
            'retentionDays' => max(1, (int) config('agovena.backups.retention_days', 30)),
            'retentionCount' => max(1, (int) config('agovena.backups.retention_count', 10)),
            'intervalOptions' => collect(BackupSchedule::INTERVALS)
                ->mapWithKeys(static fn (string $interval): array => [$interval => __('admin.backups.intervals.'.$interval)])
                ->all(),
            'schedulerHealthy' => $schedulerHealthy,
            'schedulerLastHeartbeat' => $lastHeartbeat,
        ])->layout('layouts.admin', [
            'title' => __('admin.backups.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

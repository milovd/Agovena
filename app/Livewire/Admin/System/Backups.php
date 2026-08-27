<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Backups\BackupManager;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Throwable;

final class Backups extends Component
{
    public string $lastResult = '';

    public function mount(): void
    {
        $this->authorize('backups.view');
    }

    public function createBackup(BackupManager $backupManager): void
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

    /**
     * @return array<int, array{name: string, modifiedAt: int, size: int}>
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
                if (! str_starts_with($path, $prefix.'database-') || ! str_ends_with($path, '.enc')) {
                    continue;
                }

                try {
                    $files[] = [
                        'name' => basename($path),
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

    public function render(AdminRegistrar $admin)
    {
        $allFiles = $this->backupFiles();
        $files = array_slice($allFiles, 0, 100);

        return view('livewire.admin.system.backups', [
            'files' => $files,
            'availableCount' => count($allFiles),
            'databaseDriver' => (string) config('database.connections.'.config('database.default').'.driver', 'unknown'),
            'diskName' => (string) config('agovena.backups.disk', 'local'),
            'directory' => trim((string) config('agovena.backups.directory', 'backups'), '/'),
            'retentionDays' => max(1, (int) config('agovena.backups.retention_days', 30)),
            'retentionCount' => max(1, (int) config('agovena.backups.retention_count', 10)),
        ])->layout('layouts.admin', [
            'title' => __('admin.backups.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('verifies a backup artifact through the operator command', function (): void {
    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'backups');
    $source = tempnam(sys_get_temp_dir(), 'agovena-command-verify-');
    file_put_contents($source, "SQLite format 3\000database");
    $backup = app(BackupManager::class)->backupSqlite($source);

    expect(Artisan::call('agovena:backup-verify', ['path' => $backup->path]))->toBe(0)
        ->and(Artisan::output())->toContain('verified');
    unlink($source);
});

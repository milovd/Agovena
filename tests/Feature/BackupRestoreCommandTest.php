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
    $sqlite = new SQLite3($source);
    $sqlite->exec('CREATE TABLE restore_probe (id INTEGER PRIMARY KEY)');
    $sqlite->close();
    $backup = app(BackupManager::class)->backupSqlite($source);

    expect(Artisan::call('agovena:backup-verify', ['path' => $backup->path]))->toBe(0)
        ->and(Artisan::output())->toContain('verified');
    unlink($source);
});

it('release smoke verifies the encrypted artifact created by the backup command', function (): void {
    $script = file_get_contents(base_path('scripts/smoke-extracted-release.sh'));

    expect($script)->toBeString()->toContain('agovena:backup-verify');
});

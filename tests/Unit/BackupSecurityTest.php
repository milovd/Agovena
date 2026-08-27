<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('fails closed when backup storage returns false', function (): void {
    $source = storage_path('framework/testing-backup-storage-failure.sqlite');
    file_put_contents($source, 'private database payload');

    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

    $result = app(BackupManager::class)->backupSqlite($source);

    unlink($source);

    expect($result->success)->toBeFalse()
        ->and($result->path)->toBeNull()
        ->and($result->errorCode)->toBe('storage_failed');
});

it('uses private temporary credential handling for mysql backups', function (): void {
    $source = file_get_contents(app_path('Agovena/Backups/BackupManager.php'));

    expect($source)->not->toContain('MYSQL_PWD')
        ->and($source)->not->toContain('@unlink')
        ->and($source)->not->toContain('@chmod')
        ->and($source)->toContain("fopen(\$path, 'xb')")
        ->and($source)->toContain('icacls');
});

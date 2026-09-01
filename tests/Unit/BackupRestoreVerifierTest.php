<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupManager;
use App\Agovena\Backups\BackupRestoreVerifier;
use Illuminate\Support\Facades\Storage;

it('verifies an encrypted backup artifact without exposing its payload', function (): void {
    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'backups');
    $source = tempnam(sys_get_temp_dir(), 'agovena-restore-');
    $sqlite = new SQLite3($source);
    $sqlite->exec('CREATE TABLE restore_probe (id INTEGER PRIMARY KEY)');
    $sqlite->close();

    $backup = app(BackupManager::class)->backupSqlite($source);
    $result = app(BackupRestoreVerifier::class)->verify((string) $backup->path);

    expect($result->valid)->toBeTrue()->and($result->errorCode)->toBeNull();
    unlink($source);
});

it('fails closed for an invalid backup artifact path', function (): void {
    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'backups');

    $result = app(BackupRestoreVerifier::class)->verify('backups/not-present.enc');

    expect($result->valid)->toBeFalse()->and($result->errorCode)->toBe('artifact_missing');
});

it('rejects a corrupt sqlite payload even when its file header looks valid', function (): void {
    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'backups');
    $source = tempnam(sys_get_temp_dir(), 'agovena-corrupt-');
    file_put_contents($source, "SQLite format 3\000not-a-database");

    $backup = app(BackupManager::class)->backupSqlite($source);
    $result = app(BackupRestoreVerifier::class)->verify((string) $backup->path);

    expect($result->valid)->toBeFalse()->and($result->errorCode)->toBe('database_integrity_failed');
    unlink($source);
});

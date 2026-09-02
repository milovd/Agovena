<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('creates an encrypted sqlite backup artifact and applies retention', function (): void {
    $source = storage_path('framework/testing-backup.sqlite');
    file_put_contents($source, 'private database payload');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');
    config()->set('agovena.backups.retention_days', 30);
    config()->set('agovena.backups.retention_count', 2);
    Storage::fake('local');

    $manager = app(BackupManager::class);
    $first = $manager->backupSqlite($source);
    $second = $manager->backupSqlite($source);

    expect($first->success)->toBeTrue()
        ->and($first->path)->toStartWith('testing-backups/database-')
        ->and($first->path)->toEndWith('.enc')
        ->and(Storage::disk('local')->files('testing-backups'))->toHaveCount(2);

    $encrypted = Storage::disk('local')->get($first->path);
    $decrypted = Crypt::decrypt($encrypted);
    expect($encrypted)->not->toContain('private database payload')
        ->and(gzuncompress($decrypted))->toBe('private database payload')
        ->and($second->prunedCount)->toBe(0);

    unlink($source);
});

it('fails closed when a sqlite backup source is missing', function (): void {
    Storage::fake('local');

    $result = app(BackupManager::class)->backupSqlite(storage_path('framework/missing-backup.sqlite'));

    expect($result->success)->toBeFalse()
        ->and($result->path)->toBeNull()
        ->and($result->errorCode)->toBe('source_missing');
});

it('does not prune unrelated encrypted files', function (): void {
    $source = storage_path('framework/testing-backup-unrelated.sqlite');
    file_put_contents($source, 'private database payload');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');
    config()->set('agovena.backups.retention_days', 30);
    config()->set('agovena.backups.retention_count', 1);
    Storage::fake('local');
    Storage::disk('local')->put('testing-backups/unrelated.enc', 'do not delete');
    sleep(1);

    app(BackupManager::class)->backupSqlite($source);

    expect(Storage::disk('local')->exists('testing-backups/unrelated.enc'))->toBeTrue();
    unlink($source);
});

it('does not prune recovery points belonging to another database driver', function (): void {
    $source = storage_path('framework/testing-backup-driver.sqlite');
    file_put_contents($source, 'private database payload');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');
    config()->set('agovena.backups.retention_days', 30);
    config()->set('agovena.backups.retention_count', 1);
    Storage::fake('local');
    Storage::disk('local')->put('testing-backups/database-mysql-20260826000000-old.enc', 'keep mysql');

    app(BackupManager::class)->backupSqlite($source);
    app(BackupManager::class)->backupSqlite($source);

    expect(Storage::disk('local')->exists('testing-backups/database-mysql-20260826000000-old.enc'))->toBeTrue();
    unlink($source);
});

it('deletes only encrypted database artifacts inside the configured backup directory', function (): void {
    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');
    Storage::disk('local')->put('testing-backups/database-sqlite-test.enc', 'encrypted');
    Storage::disk('local')->put('outside.enc', 'keep');

    $manager = app(BackupManager::class);

    expect($manager->deleteBackup('testing-backups/database-sqlite-test.enc')->success)->toBeTrue()
        ->and(Storage::disk('local')->exists('testing-backups/database-sqlite-test.enc'))->toBeFalse()
        ->and($manager->deleteBackup('testing-backups/../outside.enc')->errorCode)->toBe('artifact_outside_root')
        ->and(Storage::disk('local')->exists('outside.enc'))->toBeTrue();
});

it('restores a verified sqlite backup over the configured database', function (): void {
    $originalDefault = config('database.default');
    $originalConnection = config('database.connections.restore_target');

    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'testing-backups');
    config()->set('database.default', 'sqlite');

    $source = tempnam(sys_get_temp_dir(), 'agovena-restore-source-');
    $target = tempnam(sys_get_temp_dir(), 'agovena-restore-target-');
    expect($source)->not->toBeFalse()->and($target)->not->toBeFalse();

    $sourceDatabase = new SQLite3((string) $source);
    $sourceDatabase->exec('CREATE TABLE restored_records (id INTEGER PRIMARY KEY, label TEXT)');
    $sourceDatabase->exec("INSERT INTO restored_records (label) VALUES ('from-backup')");
    $sourceDatabase->close();

    $targetDatabase = new SQLite3((string) $target);
    $targetDatabase->exec('CREATE TABLE old_records (id INTEGER PRIMARY KEY)');
    $targetDatabase->close();

    config()->set('database.connections.restore_target', [
        'driver' => 'sqlite',
        'database' => (string) $target,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('database.default', 'restore_target');

    try {
        $backup = app(BackupManager::class)->backupSqlite((string) $source);
        $result = app(BackupManager::class)->restoreBackup((string) $backup->path);
        $restoredDatabase = new SQLite3((string) $target);

        expect($result->success)->toBeTrue()
            ->and($restoredDatabase->querySingle('SELECT label FROM restored_records LIMIT 1'))->toBe('from-backup')
            ->and($restoredDatabase->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE name = 'old_records'"))->toBe(0);

        $restoredDatabase->close();
    } finally {
        config()->set('database.default', $originalDefault);
        config()->set('database.connections.restore_target', $originalConnection);
        DB::purge('restore_target');
        @unlink((string) $source);
        @unlink((string) $target);
    }
});

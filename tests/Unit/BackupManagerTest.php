<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupManager;
use Illuminate\Support\Facades\Crypt;
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

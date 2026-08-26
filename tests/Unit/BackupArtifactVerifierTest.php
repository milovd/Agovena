<?php

declare(strict_types=1);

use App\Agovena\Backups\BackupArtifactVerifier;

it('verifies the required application backup artifact paths without exposing contents', function (): void {
    $root = storage_path('framework/testing-backup-artifact-'.bin2hex(random_bytes(8)));
    mkdir($root.'/storage/app/private', 0777, true);
    mkdir($root.'/storage/app/public', 0777, true);
    mkdir($root.'/database', 0777, true);
    file_put_contents($root.'/.env', "APP_KEY=[REDACTED]\n");
    file_put_contents($root.'/database/database.sqlite', 'sqlite-artifact');

    $result = app(BackupArtifactVerifier::class)->verify($root);

    expect($result->valid)->toBeTrue()
        ->and($result->missing)->toBe([])
        ->and($result->checked)->toContain('database/database.sqlite');
});

it('fails closed when backup artifacts are incomplete or outside a directory', function (): void {
    $root = storage_path('framework/incomplete-backup-artifact-'.bin2hex(random_bytes(8)));
    mkdir($root, 0777, true);

    $result = app(BackupArtifactVerifier::class)->verify($root);
    $invalid = app(BackupArtifactVerifier::class)->verify($root.'/missing');

    expect($result->valid)->toBeFalse()
        ->and($result->missing)->not->toBeEmpty()
        ->and($invalid->valid)->toBeFalse();
});

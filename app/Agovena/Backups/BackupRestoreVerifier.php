<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BackupRestoreVerifier
{
    public function verify(string $relativePath): BackupRestoreVerificationResult
    {
        $disk = Storage::disk((string) config('agovena.backups.disk', 'local'));
        $directory = trim((string) config('agovena.backups.directory', 'backups'), '/');
        $path = ltrim(trim($relativePath), '/');
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, $directory.'/')) {
            return new BackupRestoreVerificationResult(false, 'artifact_outside_root');
        }
        if (! $disk->exists($path)) {
            return new BackupRestoreVerificationResult(false, 'artifact_missing');
        }

        try {
            $encrypted = $disk->get($path);
            $compressed = Crypt::decrypt($encrypted);
            $payload = gzuncompress($compressed);
            if (! is_string($payload) || $payload === '') {
                return new BackupRestoreVerificationResult(false, 'payload_invalid');
            }
        } catch (Throwable) {
            return new BackupRestoreVerificationResult(false, 'payload_unreadable');
        }

        $isSqlite = str_starts_with($payload, 'SQLite format 3');
        $isSqlDump = str_contains($payload, 'CREATE TABLE') || str_contains($payload, 'CREATE DATABASE');
        if (! $isSqlite && ! $isSqlDump) {
            return new BackupRestoreVerificationResult(false, 'payload_type_unknown');
        }

        return new BackupRestoreVerificationResult(true);
    }
}

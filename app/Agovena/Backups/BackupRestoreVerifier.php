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
        if ($isSqlite && ! $this->hasValidSqliteIntegrity($payload)) {
            return new BackupRestoreVerificationResult(false, 'database_integrity_failed');
        }
        $isSqlDump = str_contains($payload, 'CREATE TABLE') || str_contains($payload, 'CREATE DATABASE');
        if (! $isSqlite && ! $isSqlDump) {
            return new BackupRestoreVerificationResult(false, 'payload_type_unknown');
        }

        return new BackupRestoreVerificationResult(true);
    }

    private function hasValidSqliteIntegrity(string $payload): bool
    {
        $path = tempnam(sys_get_temp_dir(), 'agovena-backup-');
        if ($path === false) {
            return false;
        }

        try {
            $written = file_put_contents($path, $payload);
            if ($written !== strlen($payload)) {
                return false;
            }

            $database = new \PDO('sqlite:'.$path, options: [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $integrity = $database->query('PRAGMA integrity_check')->fetchColumn();

            return is_string($integrity) && strtolower(trim($integrity)) === 'ok';
        } catch (Throwable) {
            return false;
        } finally {
            if (is_file($path)) {
                try {
                    unlink($path);
                } catch (Throwable) {
                    // The verification result must not expose the temporary payload.
                }
            }
        }
    }
}

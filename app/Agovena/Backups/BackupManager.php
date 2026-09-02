<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class BackupManager implements DatabaseBackupManager
{
    public function __construct(
        private readonly BackupRestoreVerifier $verifier,
    ) {}

    public function backupSqlite(string $source): BackupRunResult
    {
        if (! is_file($source) || ! is_readable($source)) {
            return new BackupRunResult(false, null, errorCode: 'source_missing');
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            return new BackupRunResult(false, null, errorCode: 'source_unreadable');
        }

        return $this->storeEncrypted($contents, 'sqlite');
    }

    public function backupConfiguredDatabase(): BackupRunResult
    {
        $connectionName = (string) config('database.default');
        $connection = (array) config('database.connections.'.$connectionName, []);
        $driver = (string) ($connection['driver'] ?? '');

        if ($driver === 'sqlite') {
            $source = (string) ($connection['database'] ?? '');
            if ($source !== '' && ! Str::startsWith($source, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $source)) {
                $source = base_path($source);
            }

            return $this->backupSqlite($source);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->backupMysql($connection);
        }

        return new BackupRunResult(false, null, errorCode: 'unsupported_driver');
    }

    public function deleteBackup(string $relativePath): BackupActionResult
    {
        $path = $this->resolveArtifactPath($relativePath);
        if ($path === null) {
            return new BackupActionResult(false, 'artifact_outside_root');
        }

        try {
            $disk = $this->backupDisk();
            if (! $disk->exists($path)) {
                return new BackupActionResult(false, 'artifact_missing');
            }

            if (! $disk->delete($path) || $disk->exists($path)) {
                return new BackupActionResult(false, 'delete_failed');
            }

            return new BackupActionResult(true);
        } catch (\Throwable) {
            return new BackupActionResult(false, 'delete_failed');
        }
    }

    public function restoreBackup(string $relativePath): BackupActionResult
    {
        $path = $this->resolveArtifactPath($relativePath);
        if ($path === null) {
            return new BackupActionResult(false, 'artifact_outside_root');
        }

        $artifactDriver = $this->artifactDriver($path);
        $connectionName = (string) config('database.default');
        $connection = (array) config('database.connections.'.$connectionName, []);
        $configuredDriver = (string) ($connection['driver'] ?? '');
        $currentDriver = $configuredDriver === 'mariadb' ? 'mysql' : $configuredDriver;

        if ($artifactDriver === null || $artifactDriver !== $currentDriver) {
            return new BackupActionResult(false, 'driver_mismatch');
        }

        $verification = $this->verifier->verify($path);
        if (! $verification->valid) {
            return new BackupActionResult(false, $verification->errorCode ?? 'verification_failed');
        }

        try {
            $payload = $this->decryptArtifact($path);
        } catch (\Throwable) {
            return new BackupActionResult(false, 'payload_unreadable');
        }

        return $artifactDriver === 'sqlite'
            ? $this->restoreSqlite($payload, $connectionName, $connection)
            : $this->restoreMysql($payload, $connection);
    }

    private function backupDisk(): Filesystem
    {
        return Storage::disk((string) config('agovena.backups.disk', 'local'));
    }

    private function resolveArtifactPath(string $relativePath): ?string
    {
        $directory = trim((string) config('agovena.backups.directory', 'backups'), '/');
        $path = ltrim(trim($relativePath), '/');
        $prefix = $directory === '' ? '' : $directory.'/';

        if ($path === '' || str_contains($path, '..') || ! Str::startsWith($path, $prefix)) {
            return null;
        }

        $filename = basename($path);
        if ($path !== $prefix.$filename || preg_match('/^database-(sqlite|mysql)-[A-Za-z0-9_-]+\.enc$/', $filename) !== 1) {
            return null;
        }

        return $path;
    }

    private function artifactDriver(string $path): ?string
    {
        if (preg_match('/^database-(sqlite|mysql)-/', basename($path), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function decryptArtifact(string $path): string
    {
        $encrypted = $this->backupDisk()->get($path);
        $compressed = Crypt::decrypt($encrypted);
        $payload = gzuncompress($compressed);

        if (! is_string($payload) || $payload === '') {
            throw new RuntimeException('Backup payload is invalid.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $connection */
    private function restoreSqlite(string $payload, string $connectionName, array $connection): BackupActionResult
    {
        $databasePath = (string) ($connection['database'] ?? '');
        if ($databasePath === '' || $databasePath === ':memory:') {
            return new BackupActionResult(false, 'database_path_unavailable');
        }

        if (! Str::startsWith($databasePath, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $databasePath)) {
            $databasePath = base_path($databasePath);
        }

        $directory = dirname($databasePath);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return new BackupActionResult(false, 'database_path_unavailable');
        }

        $temporaryPath = $directory.DIRECTORY_SEPARATOR.'.agovena-restore-'.Str::lower(Str::random(24)).'.sqlite';
        $previousPath = $directory.DIRECTORY_SEPARATOR.'.agovena-previous-'.Str::lower(Str::random(24)).'.sqlite';
        $previousMoved = false;
        $newInstalled = false;

        try {
            if (file_put_contents($temporaryPath, $payload, LOCK_EX) !== strlen($payload)) {
                return new BackupActionResult(false, 'restore_failed');
            }

            DB::purge($connectionName);
            if (! $this->removeSqliteSidecars($databasePath)) {
                return new BackupActionResult(false, 'restore_failed');
            }

            if (is_file($databasePath)) {
                if (! rename($databasePath, $previousPath)) {
                    return new BackupActionResult(false, 'restore_failed');
                }
                $previousMoved = true;
            }

            if (! rename($temporaryPath, $databasePath)) {
                return new BackupActionResult(false, 'restore_failed');
            }
            $newInstalled = true;

            if ($previousMoved && is_file($previousPath) && ! unlink($previousPath)) {
                return new BackupActionResult(false, 'restore_cleanup_failed');
            }

            return new BackupActionResult(true);
        } catch (\Throwable) {
            return new BackupActionResult(false, 'restore_failed');
        } finally {
            if (! $newInstalled && $previousMoved && is_file($previousPath) && ! is_file($databasePath)) {
                @rename($previousPath, $databasePath);
            }
            try {
                $this->removeTemporaryFile($temporaryPath);
            } catch (\Throwable) {
                // Temporary restore data must not leak into the application storage.
            }
            try {
                $this->removeTemporaryFile($previousPath);
            } catch (\Throwable) {
                // A failed cleanup is reported by the action result when possible.
            }
        }
    }

    private function removeSqliteSidecars(string $databasePath): bool
    {
        foreach ([$databasePath.'-wal', $databasePath.'-shm'] as $sidecarPath) {
            if (is_file($sidecarPath) && ! unlink($sidecarPath)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $connection */
    private function restoreMysql(string $payload, array $connection): BackupActionResult
    {
        $temporaryDirectory = storage_path('framework/private');
        $credentialsPath = $temporaryDirectory.'/restore-'.Str::lower(Str::random(24)).'.cnf';

        try {
            if (! $this->ensurePrivateDirectory($temporaryDirectory) || ! $this->writeMysqlCredentialsFile($credentialsPath, $connection)) {
                return new BackupActionResult(false, 'temporary_storage_failed');
            }

            $process = new Process([
                (string) config('agovena.backups.mysql_restore_binary', 'mysql'),
                '--defaults-extra-file='.$credentialsPath,
                (string) ($connection['database'] ?? ''),
            ], base_path(), [], $payload, 300);
            $process->run();

            return $process->isSuccessful()
                ? new BackupActionResult(true)
                : new BackupActionResult(false, 'restore_failed');
        } catch (\Throwable) {
            return new BackupActionResult(false, 'restore_failed');
        } finally {
            try {
                $this->removeTemporaryFile($credentialsPath);
            } catch (\Throwable) {
                // Do not expose credentials or cleanup details to the admin UI.
            }
        }
    }

    /** @param array<string, mixed> $connection */
    private function backupMysql(array $connection): BackupRunResult
    {
        $temporaryDirectory = storage_path('framework/private');
        $temporaryPath = $temporaryDirectory.'/backup-'.Str::lower(Str::random(24)).'.sql';
        $credentialsPath = $temporaryDirectory.'/backup-'.Str::lower(Str::random(24)).'.cnf';
        $arguments = [
            (string) config('agovena.backups.mysql_dump_binary', 'mysqldump'),
            '--defaults-extra-file='.$credentialsPath,
            '--single-transaction',
            '--routines',
            '--triggers',
            (string) ($connection['database'] ?? ''),
        ];
        $result = new BackupRunResult(false, null, errorCode: 'temporary_storage_failed');

        try {
            if ($this->ensurePrivateDirectory($temporaryDirectory) && $this->writeMysqlCredentialsFile($credentialsPath, $connection)) {
                $output = fopen($temporaryPath, 'xb');
                if ($output !== false) {
                    try {
                        if (! $this->restrictPermissions($temporaryPath, false)) {
                            $result = new BackupRunResult(false, null, errorCode: 'temporary_storage_failed');
                        } else {
                            $process = new Process($arguments, base_path(), [], null, 300);
                            $process->run(static function (string $type, string $buffer) use ($output): void {
                                if ($type === Process::OUT && fwrite($output, $buffer) === false) {
                                    throw new RuntimeException('Backup dump output could not be written.');
                                }
                            });

                            if (! $process->isSuccessful()) {
                                $result = new BackupRunResult(false, null, errorCode: 'dump_failed');
                            } else {
                                $contents = file_get_contents($temporaryPath);
                                $result = $contents === false
                                    ? new BackupRunResult(false, null, errorCode: 'dump_unreadable')
                                    : $this->storeEncrypted($contents, 'mysql');
                            }
                        }
                    } finally {
                        fclose($output);
                    }
                }
            }
        } catch (\Throwable) {
            $result = new BackupRunResult(false, null, errorCode: 'dump_failed');
        }

        try {
            $this->removeTemporaryFile($temporaryPath);
            $this->removeTemporaryFile($credentialsPath);
        } catch (\Throwable) {
            return new BackupRunResult(false, null, errorCode: 'temporary_cleanup_failed');
        }

        return $result;
    }

    /** @param array<string, mixed> $connection */
    private function writeMysqlCredentialsFile(string $path, array $connection): bool
    {
        $escape = static fn (mixed $value): string => addcslashes((string) $value, "\\\"\n\r");
        $contents = implode("\n", [
            '[client]',
            'host="'.$escape($connection['host'] ?? '127.0.0.1').'"',
            'port="'.$escape($connection['port'] ?? 3306).'"',
            'user="'.$escape($connection['username'] ?? '').'"',
            'password="'.$escape($connection['password'] ?? '').'"',
            '',
        ]);
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            return false;
        }

        try {
            $remaining = $contents;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    return false;
                }
                $remaining = substr($remaining, $written);
            }

            if (! fflush($handle)) {
                return false;
            }
        } finally {
            fclose($handle);
        }

        return $this->restrictPermissions($path, false) && is_readable($path);
    }

    private function ensurePrivateDirectory(string $path): bool
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            return false;
        }

        return $this->restrictPermissions($path, true);
    }

    private function restrictPermissions(string $path, bool $directory): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $identity = trim((string) (getenv('USERNAME') ?: getenv('USER')));
            if ($identity === '') {
                return false;
            }

            $process = new Process([
                'icacls',
                $path,
                '/inheritance:r',
                '/grant:r',
                $identity.':F',
            ]);
            $process->run();

            return $process->isSuccessful();
        }

        $mode = $directory ? 0700 : 0600;
        if (! chmod($path, $mode)) {
            return false;
        }

        clearstatcache(true, $path);
        $permissions = fileperms($path);

        return $permissions !== false && ($permissions & 0777) === $mode;
    }

    private function removeTemporaryFile(string $path): void
    {
        clearstatcache(true, $path);
        if (! is_file($path)) {
            return;
        }

        if (! unlink($path)) {
            throw new RuntimeException('Temporary backup file could not be removed.');
        }

        clearstatcache(true, $path);
        if (is_file($path)) {
            throw new RuntimeException('Temporary backup file remains after cleanup.');
        }
    }

    private function storeEncrypted(string $contents, string $driver): BackupRunResult
    {
        $diskName = (string) config('agovena.backups.disk', 'local');
        $directory = trim((string) config('agovena.backups.directory', 'backups'), '/');
        $disk = Storage::disk($diskName);
        $path = ($directory === '' ? '' : $directory.'/').'database-'.$driver.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(12)).'.enc';
        $compressed = gzcompress($contents, 9);
        if ($compressed === false) {
            return new BackupRunResult(false, null, errorCode: 'compression_failed');
        }

        try {
            if (! $disk->put($path, Crypt::encrypt($compressed)) || ! $disk->exists($path)) {
                return new BackupRunResult(false, null, errorCode: 'storage_failed');
            }
        } catch (\Throwable) {
            return new BackupRunResult(false, null, errorCode: 'storage_failed');
        }

        return new BackupRunResult(true, $path, $this->prune($disk, $directory, $driver));
    }

    private function prune(Filesystem $disk, string $directory, string $driver): int
    {
        $files = array_values(array_filter(
            $disk->files($directory),
            static fn (string $path): bool => Str::startsWith($path, ($directory === '' ? '' : $directory.'/').'database-'.$driver.'-')
                && Str::endsWith($path, '.enc'),
        ));
        $retentionDays = max(1, (int) config('agovena.backups.retention_days', 30));
        $retentionCount = max(1, (int) config('agovena.backups.retention_count', 10));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;
        $remaining = [];

        foreach ($files as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            } else {
                $remaining[] = $file;
            }
        }

        usort($remaining, static fn (string $left, string $right): int => $disk->lastModified($right) <=> $disk->lastModified($left));
        foreach (array_slice($remaining, $retentionCount) as $file) {
            $disk->delete($file);
            $deleted++;
        }

        return $deleted;
    }
}

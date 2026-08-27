<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class BackupManager implements DatabaseBackupManager
{
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
        $path = $directory.'/database-'.$driver.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(12)).'.enc';
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

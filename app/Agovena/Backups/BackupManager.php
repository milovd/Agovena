<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $temporaryPath = storage_path('framework/backup-'.Str::lower(Str::random(24)).'.sql');
        $arguments = [
            (string) config('agovena.backups.mysql_dump_binary', 'mysqldump'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) ($connection['username'] ?? ''),
            (string) ($connection['database'] ?? ''),
        ];
        $environment = [];
        if (filled($connection['password'] ?? null)) {
            $environment['MYSQL_PWD'] = (string) $connection['password'];
        }

        try {
            $output = fopen($temporaryPath, 'wb');
            if ($output === false) {
                return new BackupRunResult(false, null, errorCode: 'temporary_storage_failed');
            }

            try {
                $process = new Process($arguments, base_path(), $environment, null, 300);
                $process->run(static function (string $type, string $buffer) use ($output): void {
                    if ($type === Process::OUT) {
                        fwrite($output, $buffer);
                    }
                });
            } finally {
                fclose($output);
            }

            if (! $process->isSuccessful()) {
                return new BackupRunResult(false, null, errorCode: 'dump_failed');
            }

            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                return new BackupRunResult(false, null, errorCode: 'dump_unreadable');
            }

            return $this->storeEncrypted($contents, 'mysql');
        } catch (\Throwable) {
            return new BackupRunResult(false, null, errorCode: 'dump_failed');
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
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
            $disk->put($path, Crypt::encrypt($compressed));
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

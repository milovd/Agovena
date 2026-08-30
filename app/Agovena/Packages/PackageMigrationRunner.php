<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Support\RecoversTestTransaction;
use Closure;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class PackageMigrationRunner
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    /** @return array{journal: string|null, migrations: list<string>} */
    public function run(string $packageId, string $packageRoot, ?string $journal = null): array
    {
        $path = $this->resolveMigrationPath($packageId, $packageRoot);
        if ($path === null) {
            return ['journal' => null, 'migrations' => []];
        }

        return $this->withJournalLock(function () use ($packageId, $packageRoot, $path, $journal): array {
            $ownsJournal = $journal === null;
            $journal ??= $this->prepareUnlocked($packageId, $packageRoot, $path);

            $state = $this->read($journal);
            if ($state['package_id'] !== $packageId || $state['path'] !== $path) {
                throw new RuntimeException('Package migration journal does not match the package.');
            }
            $this->updateUnlocked($journal, ['status' => 'running']);

            try {
                $this->assertMigrationSourceAvailable($state['path']);
                $ranFiles = $this->migrator->run([$path]);
                $executed = [];
                foreach ($ranFiles as $ranFile) {
                    $executed[] = $this->migrator->getMigrationName($ranFile);
                }
                $this->updateUnlocked($journal, [
                    'status' => 'applied',
                    'executed' => $executed,
                ]);

                if ($ownsJournal) {
                    $this->updateUnlocked($journal, ['status' => 'committed']);
                    $this->delete($journal);
                }

                return ['journal' => $journal, 'migrations' => $executed];
            } catch (\Throwable $exception) {
                if ($ownsJournal) {
                    try {
                        $this->rollbackUnlocked($journal);
                    } catch (\Throwable $rollbackException) {
                        report($rollbackException);
                    }
                }

                throw $exception;
            } finally {
                RecoversTestTransaction::afterDdl();
            }
        });
    }

    public function prepare(string $packageId, string $packageRoot): ?string
    {
        $path = $this->resolveMigrationPath($packageId, $packageRoot);
        if ($path === null) {
            return null;
        }

        return $this->withJournalLock(fn (): string => $this->prepareUnlocked($packageId, $packageRoot, $path));
    }

    public function commit(string $journal): void
    {
        $this->update($journal, ['status' => 'committed']);
    }

    public function rollback(string $journal): void
    {
        $this->withJournalLock(fn (): null => $this->rollbackUnlocked($journal));
    }

    private function rollbackUnlocked(string $journal): void
    {
        $state = $this->read($journal);
        if ($state['status'] === 'committed') {
            throw new RuntimeException('Committed package migrations cannot be rolled back.');
        }

        $this->assertMigrationSourceAvailable($state['path']);

        if (in_array($state['status'], ['running', 'applied'], true)) {
            $this->rollbackExact($state);
            $packageNames = array_keys($this->migrator->getMigrationFiles([$state['path']]));
            $remaining = array_intersect(
                array_diff($this->migrationNames(), $state['before']),
                $packageNames,
            );
            if ($remaining !== []) {
                throw new RuntimeException('Package migration rollback left applied migrations behind.');
            }
        }

        $this->updateUnlocked($journal, ['status' => 'rolled_back']);
        $this->delete($journal);
        RecoversTestTransaction::afterDdl();
    }

    /** @param array{status: string, package_id: string, package_root: string, path: string, batch: int, before: list<string>, executed: list<string>} $state */
    private function rollbackExact(array $state): void
    {
        $this->assertMigrationSourceAvailable($state['path']);
        $files = $this->migrator->getMigrationFiles([$state['path']]);
        $packageNames = array_keys($files);
        $targetNames = array_values(array_intersect($state['executed'], $packageNames));
        if ($targetNames === []) {
            $targetNames = array_values(array_intersect(
                array_diff($this->migrationNames(), $state['before']),
                $packageNames,
            ));
        }
        if ($targetNames === []) {
            return;
        }

        $records = array_values(array_filter(
            $this->migrator->getRepository()->getMigrations(PHP_INT_MAX),
            static fn (object $migration): bool => in_array($migration->migration, $targetNames, true),
        ));
        if ($records === []) {
            return;
        }

        $rollbackMigrations = Closure::bind(
            static fn (Migrator $migrator, array $migrations, string $path): array => $migrator->rollbackMigrations(
                $migrations,
                [$path],
                [],
            ),
            null,
            Migrator::class,
        );
        $lock = Cache::lock(
            'agovena:database-migrations',
            max(60, (int) config('agovena.packages.migration_lock_seconds', 3600)),
        );
        $lock->block(60);
        try {
            $rollbackMigrations($this->migrator, $records, $state['path']);
        } finally {
            $lock->release();
        }
    }

    public function reconcile(): void
    {
        $this->withJournalLock(function (): null {
            $root = storage_path('app/packages').DIRECTORY_SEPARATOR.'.migration-operations';
            if (! is_dir($root) || is_link($root)) {
                return null;
            }

            foreach (glob($root.DIRECTORY_SEPARATOR.'migration-*.json') ?: [] as $journal) {
                $state = $this->read($journal);
                if ($state['status'] === 'committed') {
                    $this->delete($journal);

                    continue;
                }
                $this->rollbackUnlocked($journal);
            }

            return null;
        });
    }

    private function prepareUnlocked(string $packageId, string $packageRoot, string $path): string
    {
        $root = storage_path('app/packages').DIRECTORY_SEPARATOR.'.migration-operations';
        File::ensureDirectoryExists($root);
        if (is_link($root)) {
            throw new RuntimeException('Package migration journal root may not use symbolic links.');
        }

        $journal = $root.DIRECTORY_SEPARATOR.'migration-'.bin2hex(random_bytes(12)).'.json';
        $this->write($journal, [
            'status' => 'prepared',
            'package_id' => $packageId,
            'package_root' => $this->normalize(realpath($packageRoot) ?: $packageRoot),
            'path' => $path,
            'batch' => $this->nextBatchNumber(),
            'before' => $this->migrationNames(),
            'executed' => [],
        ]);

        return $journal;
    }

    private function withJournalLock(Closure $callback): mixed
    {
        $lock = Cache::lock(
            'agovena:package-migration-journal',
            max(60, (int) config('agovena.packages.migration_lock_seconds', 3600)),
        );
        $lock->block(60);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /** @return array{status: string, package_id: string, package_root: string, path: string, batch: int, before: list<string>, executed: list<string>} */
    private function read(string $journal): array
    {
        $root = storage_path('app/packages').DIRECTORY_SEPARATOR.'.migration-operations';
        $this->assertManagedPath($journal, $root);
        $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ! in_array($payload['status'] ?? null, ['prepared', 'running', 'applied', 'committed', 'rolled_back'], true)
            || ! is_string($payload['package_id'] ?? null)
            || $payload['package_id'] === ''
            || ! is_string($payload['package_root'] ?? null)
            || ! is_string($payload['path'] ?? null)
            || ! is_int($payload['batch'] ?? null)
            || ! is_array($payload['before'] ?? null)
            || ! is_array($payload['executed'] ?? null)
            || ! $this->allStrings($payload['before'])
            || ! $this->allStrings($payload['executed'])
        ) {
            throw new RuntimeException('Package migration journal is invalid.');
        }

        $packageRoot = $this->normalize($payload['package_root']);
        $expectedPath = $this->normalize($packageRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations');
        if ($this->normalize($payload['path']) !== $expectedPath) {
            throw new RuntimeException('Package migration path is not canonical.');
        }

        return [
            'status' => $payload['status'],
            'package_id' => $payload['package_id'],
            'package_root' => $packageRoot,
            'path' => $expectedPath,
            'batch' => $payload['batch'],
            'before' => array_values($payload['before']),
            'executed' => array_values($payload['executed']),
        ];
    }

    /** @param array<string, mixed> $changes */
    private function update(string $journal, array $changes): void
    {
        $this->withJournalLock(function () use ($journal, $changes): void {
            $this->updateUnlocked($journal, $changes);
        });
    }

    /** @param array<string, mixed> $changes */
    private function updateUnlocked(string $journal, array $changes): void
    {
        $state = $this->read($journal);
        foreach ($changes as $key => $value) {
            if (! in_array($key, ['status', 'executed'], true)) {
                throw new RuntimeException('Package migration journal field is invalid.');
            }
            if ($key === 'status' && ! in_array($value, ['prepared', 'running', 'applied', 'committed', 'rolled_back'], true)) {
                throw new RuntimeException('Package migration journal status is invalid.');
            }
            if ($key === 'executed' && (! is_array($value) || ! $this->allStrings($value))) {
                throw new RuntimeException('Package migration journal execution list is invalid.');
            }
            $state[$key] = $value;
        }
        $this->write($journal, $state);
    }

    private function delete(string $journal): void
    {
        $root = storage_path('app/packages').DIRECTORY_SEPARATOR.'.migration-operations';
        $this->assertManagedPath($journal, $root);
        if (File::exists($journal) && ! File::delete($journal)) {
            throw new RuntimeException('Package migration journal could not be removed.');
        }
    }

    private function resolveMigrationPath(string $packageId, string $packageRoot): ?string
    {
        if (! is_dir($packageRoot) || is_link($packageRoot)) {
            throw new RuntimeException("Package [{$packageId}] root is not a safe directory.");
        }
        $resolvedRoot = realpath($packageRoot);
        if ($resolvedRoot === false) {
            throw new RuntimeException("Package [{$packageId}] root could not be resolved.");
        }
        $path = $resolvedRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path)) {
            return null;
        }
        $resolved = realpath($path);
        if ($resolved === false || $this->normalize($resolved) !== $this->normalize($path)) {
            throw new RuntimeException("Package [{$packageId}] migrations path is not canonical.");
        }
        if (is_link($resolved)) {
            throw new RuntimeException('Package migrations may not use symbolic links.');
        }

        return $this->normalize($resolved);
    }

    private function assertMigrationSourceAvailable(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            throw new RuntimeException('Package migration source is missing; recovery journal retained.');
        }

        $files = $this->migrator->getMigrationFiles([$path]);
        if ($files === []) {
            throw new RuntimeException('Package migration source is empty; recovery journal retained.');
        }
    }

    /** @param array<mixed> $values */
    private function allStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function write(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $targetExisted = File::exists($path);
        $temporary = tempnam(dirname($path), '.migration-tmp-');
        if ($temporary === false || file_put_contents($temporary, $encoded, LOCK_EX) === false) {
            if (is_string($temporary) && File::exists($temporary)) {
                File::delete($temporary);
            }
            throw new RuntimeException('Package migration journal could not be written.');
        }

        for ($attempt = 0; $attempt < 4; $attempt++) {
            if (@rename($temporary, $path)) {
                return;
            }
            usleep(100_000);
        }

        if (! $targetExisted && ! File::exists($path)) {
            $handle = @fopen($path, 'xb');
            if ($handle !== false) {
                try {
                    $offset = 0;
                    $length = strlen($encoded);
                    while ($offset < $length) {
                        $written = fwrite($handle, substr($encoded, $offset));
                        if ($written === false || $written === 0) {
                            throw new RuntimeException('Package migration journal could not be written.');
                        }
                        $offset += $written;
                    }
                    if (! fflush($handle)) {
                        throw new RuntimeException('Package migration journal could not be flushed.');
                    }
                    if (function_exists('fsync') && ! fsync($handle)) {
                        throw new RuntimeException('Package migration journal could not be synced.');
                    }
                } catch (\Throwable $exception) {
                    fclose($handle);
                    File::delete($path);
                    if (File::exists($temporary)) {
                        File::delete($temporary);
                    }
                    throw $exception;
                }
                fclose($handle);
                if (File::exists($temporary)) {
                    File::delete($temporary);
                }

                return;
            }
        }

        if (File::exists($temporary)) {
            File::delete($temporary);
        }
        throw new RuntimeException('Package migration journal could not be written.');
    }

    private function assertManagedPath(string $path, string $root): void
    {
        $normalizedPath = $this->normalize($path);
        $normalizedRoot = $this->normalize(realpath($root) ?: $root);
        if ($normalizedPath === $normalizedRoot || ! str_starts_with($normalizedPath, $normalizedRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Package migration journal path is outside the managed root.');
        }
        if (is_link($path)) {
            throw new RuntimeException('Package migration journal paths may not use symbolic links.');
        }
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    /** @return list<string> */
    private function migrationNames(): array
    {
        return array_values(DB::table($this->migrationTable())->orderBy('migration')->pluck('migration')->all());
    }

    private function nextBatchNumber(): int
    {
        return ((int) DB::table($this->migrationTable())->max('batch')) + 1;
    }

    private function migrationTable(): string
    {
        $configured = config('database.migrations');
        $table = is_array($configured) ? ($configured['table'] ?? 'migrations') : $configured;
        if (! is_string($table) || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('Migration repository table is invalid.');
        }

        return $table;
    }
}

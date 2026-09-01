<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class LockedMigrator extends Migrator
{
    private ?string $sqliteBootstrapConnection = null;

    public function run($paths = [], array $options = [])
    {
        return $this->withMigrationLock(fn () => parent::run($paths, $options));
    }

    public function rollback($paths = [], array $options = [])
    {
        return $this->withMigrationLock(fn () => parent::rollback($paths, $options));
    }

    private function withMigrationLock(Closure $callback): mixed
    {
        $bootstrapConnection = $this->bootstrapLockConnection();
        if ($bootstrapConnection !== null) {
            return $this->withBootstrapDatabaseLock($bootstrapConnection, $callback);
        }

        return $this->withCacheLock((string) config('cache.default', 'database'), $callback);
    }

    private function withCacheLock(string $storeName, Closure $callback): mixed
    {
        $store = Cache::store($storeName)->getStore();

        if (! $store instanceof LockProvider) {
            throw new RuntimeException('Configured migration lock store does not support locks.');
        }

        $lock = $store->lock(
            'agovena:database-migrations',
            max(60, (int) config('agovena.packages.migration_lock_seconds', 3600)),
        );
        $lock->block(60);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function bootstrapLockConnection(): ?string
    {
        $defaultStore = (string) config('cache.default', 'database');
        $defaultDriver = (string) config("cache.stores.{$defaultStore}.driver", $defaultStore);

        if ($defaultDriver !== 'database') {
            return null;
        }

        $databaseStore = (array) config("cache.stores.{$defaultStore}", []);
        $configuredConnection = $databaseStore['lock_connection'] ?? null;
        $defaultConnection = config('database.default', 'sqlite');
        $databaseConnections = (array) config('database.connections', []);
        $connection = is_string($configuredConnection)
            && array_key_exists($configuredConnection, $databaseConnections)
            ? $configuredConnection
            : $defaultConnection;
        if (! is_string($connection) || trim($connection) === '' || ! array_key_exists($connection, $databaseConnections)) {
            throw new RuntimeException(sprintf(
                'The configured migration lock connection must be a non-empty string, got [%s].',
                get_debug_type($connection),
            ));
        }
        $table = (string) ($databaseStore['lock_table'] ?? 'cache_locks');

        try {
            if (Schema::connection($connection)->hasTable($table)) {
                return null;
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to determine migration lock availability.', previous: $exception);
        }

        return $connection;
    }

    private function withBootstrapDatabaseLock(string $connection, Closure $callback): mixed
    {
        $driver = strtolower((string) config("database.connections.{$connection}.driver", $connection));

        return match ($driver) {
            'mysql', 'mariadb' => $this->withMysqlAdvisoryLock($connection, $callback),
            'pgsql' => $this->withPostgresAdvisoryLock($connection, $callback),
            'sqlsrv' => $this->withSqlServerAdvisoryLock($connection, $callback),
            'sqlite' => $this->withSqliteDatabaseLock($connection, $callback),
            default => throw new RuntimeException(sprintf(
                'No supported bootstrap migration lock is available for connection [%s] and driver [%s].',
                $connection,
                $driver,
            )),
        };
    }

    private function withMysqlAdvisoryLock(string $connection, Closure $callback): mixed
    {
        $database = DB::connection($connection);
        $acquired = $database->selectOne(
            'SELECT GET_LOCK(?, ?) AS acquired',
            ['agovena:database-migrations', 60],
        );
        if ((int) ($acquired->acquired ?? 0) !== 1) {
            throw new RuntimeException('Unable to acquire the database migration advisory lock.');
        }

        return $this->withAdvisoryRelease($callback, function () use ($database): void {
            $released = $database->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                ['agovena:database-migrations'],
            );
            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('Unable to release the database migration advisory lock.');
            }
        });
    }

    private function withSqliteDatabaseLock(string $connection, Closure $callback): mixed
    {
        $database = DB::connection($connection);
        $database->statement('PRAGMA busy_timeout = 60000');
        $database->statement('BEGIN IMMEDIATE TRANSACTION');
        $this->sqliteBootstrapConnection = $connection;

        try {
            $result = $callback();
            $database->statement('COMMIT');

            return $result;
        } catch (Throwable $exception) {
            try {
                $database->statement('ROLLBACK');
            } catch (Throwable) {
                // Preserve the migration failure when rollback itself is unavailable.
            }

            throw $exception;
        } finally {
            $this->sqliteBootstrapConnection = null;
        }
    }

    protected function runMigration($migration, $method, $name = null)
    {
        $connection = $this->resolveConnection($migration->getConnection());
        if ($this->sqliteBootstrapConnection !== $connection->getName() || ! $migration->withinTransaction) {
            parent::runMigration($migration, $method, $name);

            return;
        }

        if (method_exists($migration, $method)) {
            $this->fireMigrationEvent(new MigrationStarted($migration, $method, $name));
            $this->runMethod($connection, $migration, $method);
            $this->fireMigrationEvent(new MigrationEnded($migration, $method, $name));
        }
    }

    private function withPostgresAdvisoryLock(string $connection, Closure $callback): mixed
    {
        $database = DB::connection($connection);
        $timeout = max(1, (int) config('agovena.packages.migration_lock_seconds', 3600));
        $deadline = microtime(true) + $timeout;
        do {
            $acquired = $database->selectOne(
                'SELECT pg_try_advisory_lock(hashtext(?)) AS acquired',
                ['agovena:database-migrations'],
            );
            if (filter_var($acquired->acquired ?? false, FILTER_VALIDATE_BOOL)) {
                break;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        if (! filter_var($acquired->acquired ?? false, FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Unable to acquire the database migration advisory lock within the configured timeout.');
        }

        return $this->withAdvisoryRelease($callback, function () use ($database): void {
            $released = $database->selectOne(
                'SELECT pg_advisory_unlock(hashtext(?)) AS released',
                ['agovena:database-migrations'],
            );
            if (! filter_var($released->released ?? false, FILTER_VALIDATE_BOOL)) {
                throw new RuntimeException('Unable to release the database migration advisory lock.');
            }
        });
    }

    private function withSqlServerAdvisoryLock(string $connection, Closure $callback): mixed
    {
        $database = DB::connection($connection);
        $acquired = $database->selectOne(
            "DECLARE @result int; EXEC @result = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = ?; SELECT @result AS acquired;",
            ['agovena:database-migrations', 60000],
        );
        if ((int) ($acquired->acquired ?? -999) < 0) {
            throw new RuntimeException('Unable to acquire the database migration advisory lock.');
        }

        return $this->withAdvisoryRelease($callback, function () use ($database): void {
            $released = $database->selectOne(
                "DECLARE @result int; EXEC @result = sp_releaseapplock @Resource = ?, @LockOwner = 'Session'; SELECT @result AS released;",
                ['agovena:database-migrations'],
            );
            if ((int) ($released->released ?? -999) < 0) {
                throw new RuntimeException('Unable to release the database migration advisory lock.');
            }
        });
    }

    private function withAdvisoryRelease(Closure $callback, Closure $release): mixed
    {
        $result = null;
        $callbackException = null;
        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $callbackException = $exception;
        }

        try {
            $release();
        } catch (Throwable $releaseException) {
            if ($callbackException !== null) {
                report($releaseException);
                throw $callbackException;
            }

            throw $releaseException;
        }

        if ($callbackException !== null) {
            throw $callbackException;
        }

        return $result;
    }
}

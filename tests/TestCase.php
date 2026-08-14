<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * MariaDB/MySQL cannot roll back DDL from Module enablement. Truncate
     * between tests instead of wrapping them in a transaction.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->migrateDatabases();
            $this->app[Kernel::class]->setArtisan(null);
            $this->updateLocalCacheOfInMemoryDatabases();
            RefreshDatabaseState::$migrated = true;

            if ($this->usesPersistentSqlServer()) {
                return;
            }
        }

        if ($this->usesPersistentSqlServer()) {
            $this->truncatePersistentSqlTables();

            return;
        }

        $this->beginDatabaseTransaction();
    }

    protected function usesPersistentSqlServer(): bool
    {
        return in_array($this->app['db']->connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    protected function truncatePersistentSqlTables(): void
    {
        $connection = $this->app['db']->connection();
        $schema = $connection->getSchemaBuilder();

        $schema->withoutForeignKeyConstraints(function () use ($connection, $schema): void {
            foreach ($schema->getTableListing() as $table) {
                $name = str_replace(['"', "'", '`'], '', (string) $table);
                if (str_contains($name, '.')) {
                    $name = substr($name, (int) strrpos($name, '.') + 1);
                }
                if (in_array($name, ['migrations'], true)) {
                    continue;
                }

                $connection->table($name)->truncate();
            }
        });
    }
}

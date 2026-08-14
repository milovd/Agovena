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
     * Module enablement runs DDL. MySQL/MariaDB cannot roll that back, so
     * leftover enabled modules leak into later tests. Rebuild the schema
     * for each persistent SQL test instead of wrapping in a transaction.
     */
    protected function refreshTestDatabase()
    {
        if ($this->usesPersistentSqlServer()) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);

            return;
        }

        if (! RefreshDatabaseState::$migrated) {
            $this->migrateDatabases();
            $this->app[Kernel::class]->setArtisan(null);
            $this->updateLocalCacheOfInMemoryDatabases();
            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function usesPersistentSqlServer(): bool
    {
        return in_array($this->app['db']->connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}

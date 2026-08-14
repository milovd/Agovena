<?php

namespace Tests;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
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
            $this->resetPackageRuntime();

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

    /**
     * Application boot may have registered packages from leftover rows before
     * migrate:fresh. Rebuild from the now-empty schema so fakes can bind first.
     */
    protected function resetPackageRuntime(): void
    {
        $this->app->make(ExtensionManager::class)->rebuildRuntime();
        $this->app->make(ModuleManager::class)->bootEnabled();
    }
}

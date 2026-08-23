<?php

namespace Tests;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\OptionalPackagesPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->refreshOptionalPackageDiscovery();
        $this->isolateEnabledPackages();
    }

    protected function refreshOptionalPackageDiscovery(): void
    {
        if (OptionalPackagesPath::root() === null) {
            return;
        }

        app(ModuleManager::class)->refresh();
        app(ExtensionManager::class)->refresh();
    }

    /**
     * Module/extension enablement that committed through MariaDB DDL must not
     * leak into the next Feature test. RefreshDatabase already remigrates when
     * the previous test left no open transaction; this keeps isEnabled() clean
     * even when leftover rows survive inside the current transaction.
     */
    protected function isolateEnabledPackages(): void
    {
        if (Schema::hasTable('agovena_modules')) {
            DB::table('agovena_modules')->where('enabled', true)->update(['enabled' => false]);
        }

        if (Schema::hasTable('agovena_extensions')) {
            DB::table('agovena_extensions')->where('enabled', true)->update(['enabled' => false]);
        }
    }
}

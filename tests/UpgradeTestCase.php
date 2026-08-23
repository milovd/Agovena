<?php

declare(strict_types=1);

namespace Tests;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\OptionalPackagesPath;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class UpgradeTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->refreshOptionalPackageDiscovery();

        // SQLite :memory: is discarded when Feature tests disconnect.
        // MariaDB keeps leftover rows/module tables. Rebuild from a clean schema.
        $this->artisan('migrate:fresh');
    }

    protected function refreshOptionalPackageDiscovery(): void
    {
        if (OptionalPackagesPath::root() === null) {
            return;
        }

        app(ModuleManager::class)->refresh();
        app(ExtensionManager::class)->refresh();
    }
}

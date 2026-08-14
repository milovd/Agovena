<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class UpgradeTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // SQLite :memory: is discarded when Feature tests disconnect.
        // MariaDB keeps leftover rows/module tables. Rebuild from a clean schema.
        $this->artisan('migrate:fresh');
    }
}

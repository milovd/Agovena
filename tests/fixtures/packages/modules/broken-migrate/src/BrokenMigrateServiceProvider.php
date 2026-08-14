<?php

declare(strict_types=1);

namespace Agovena\Modules\BrokenMigrate;

use App\Agovena\Modules\Contracts\Module;
use Illuminate\Support\ServiceProvider;

final class BrokenMigrateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BrokenMigrateModule::class);
    }

    public function module(): Module
    {
        return $this->app->make(BrokenMigrateModule::class);
    }
}

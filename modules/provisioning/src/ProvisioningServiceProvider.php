<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning;

use App\Agovena\Modules\Contracts\Module;
use Illuminate\Support\ServiceProvider;

final class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProvisioningModule::class);
        $this->app->singleton(ProvisioningService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'provisioning');
    }

    public function module(): Module
    {
        return $this->app->make(ProvisioningModule::class);
    }
}

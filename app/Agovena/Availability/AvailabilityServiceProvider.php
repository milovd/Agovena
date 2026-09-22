<?php

declare(strict_types=1);

namespace App\Agovena\Availability;

use App\Agovena\Catalog\Contracts\ProductStock;
use Illuminate\Support\ServiceProvider;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CapacityProviderRegistry::class);
        $this->app->singleton(AvailabilityCapability::class);
        $this->app->singleton(InventoryService::class);
        $this->app->singleton(ProductStock::class, CatalogProductStock::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/resources/database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'inventory');
    }
}

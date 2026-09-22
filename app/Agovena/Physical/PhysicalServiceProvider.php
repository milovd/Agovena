<?php

declare(strict_types=1);

namespace App\Agovena\Physical;

use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Fulfillment\OrderFulfillmentPresenter;
use Illuminate\Support\ServiceProvider;

final class PhysicalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PhysicalCapability::class);
        $this->app->singleton(ShippingRateCalculator::class);
        $this->app->singleton(ShipmentService::class);
        $this->app->singleton(ReturnRequestService::class);
        $this->app->singleton(ModuleShippingQuoteResolver::class);
        $this->app->singleton(ShippingOrderFulfillmentPresenter::class);

        $this->app->singleton(ShippingQuoteResolver::class, ModuleShippingQuoteResolver::class);
        $this->app->singleton(OrderFulfillmentPresenter::class, ShippingOrderFulfillmentPresenter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/resources/database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'shipping');
    }
}

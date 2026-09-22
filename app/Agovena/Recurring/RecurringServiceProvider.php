<?php

declare(strict_types=1);

namespace App\Agovena\Recurring;

use App\Agovena\Subscriptions\ProcessesSubscriptionRenewals;
use Illuminate\Support\ServiceProvider;

final class RecurringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecurringCapability::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->bind(ProcessesSubscriptionRenewals::class, SubscriptionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/resources/database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'subscriptions');
    }
}

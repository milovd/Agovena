<?php

declare(strict_types=1);

namespace Agovena\Modules\Sample;

use App\Agovena\Modules\Contracts\Module;
use Illuminate\Support\ServiceProvider;

final class SampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SampleModule::class);
    }

    public function module(): Module
    {
        return $this->app->make(SampleModule::class);
    }
}

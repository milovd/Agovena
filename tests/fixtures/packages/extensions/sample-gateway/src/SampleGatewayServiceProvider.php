<?php

declare(strict_types=1);

namespace Agovena\Extensions\SampleGateway;

use App\Agovena\Extensions\Contracts\Extension;
use Illuminate\Support\ServiceProvider;

final class SampleGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SampleGatewayExtension::class);
    }

    public function extension(): Extension
    {
        return $this->app->make(SampleGatewayExtension::class);
    }
}

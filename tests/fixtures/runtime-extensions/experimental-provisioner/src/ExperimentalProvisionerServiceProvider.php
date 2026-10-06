<?php

declare(strict_types=1);

namespace Agovena\Extensions\ExperimentalProvisioner;

use App\Agovena\Extensions\Contracts\Extension;
use Illuminate\Support\ServiceProvider;

final class ExperimentalProvisionerServiceProvider extends ServiceProvider
{
    public const BOOTED_FLAG = 'testing.experimental-provisioner.booted';

    public function register(): void
    {
        $this->app->instance(self::BOOTED_FLAG, true);
        $this->app->singleton(ExperimentalProvisionerExtension::class);
    }

    public function extension(): Extension
    {
        return $this->app->make(ExperimentalProvisionerExtension::class);
    }
}

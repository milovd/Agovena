<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ServiceInstanceInfo;

/**
 * Optional lifecycle operations for provisioning Extensions.
 * Core and the Provisioning Module must not import provider SDKs.
 */
interface ProvisionerLifecycle
{
    public function provision(ServiceInstanceInfo $instance): void;

    public function poll(ServiceInstanceInfo $instance): ServiceInstanceInfo;

    public function activate(ServiceInstanceInfo $instance): void;

    public function suspend(ServiceInstanceInfo $instance): void;

    public function unsuspend(ServiceInstanceInfo $instance): void;

    public function terminate(ServiceInstanceInfo $instance): void;

    /** @param string|array<string, mixed> $plan */
    public function changePlan(ServiceInstanceInfo $instance, string|array $plan): void;

    public function syncStatus(ServiceInstanceInfo $instance): ServiceInstanceInfo;
}

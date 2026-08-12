<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ProvisionerAction;
use App\Agovena\Provisioning\ServiceInstanceInfo;

/**
 * Provisioning Extensions implement this interface only when they expose customer actions.
 */
interface ProvisionerActions
{
    /** @return list<ProvisionerAction> */
    public function actions(ServiceInstanceInfo $instance): array;

    public function runAction(ServiceInstanceInfo $instance, string $actionId): void;
}

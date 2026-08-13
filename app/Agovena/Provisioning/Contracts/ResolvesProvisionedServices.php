<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Models\Customer;

/**
 * Bound by the Provisioning Module. Core orchestrates actions without loading Module models.
 */
interface ResolvesProvisionedServices
{
    public function resolveForCustomer(Customer $customer, int $instanceId): ?ServiceInstanceInfo;
}

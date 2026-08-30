<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ProvisioningStockContext;

interface ChecksProvisioningStockVector extends ChecksProvisioningStock
{
    /**
     * @param  array<string, int|float>  $reservedRequirements
     */
    public function assertStockVector(ProvisioningStockContext $context, array $reservedRequirements): void;
}

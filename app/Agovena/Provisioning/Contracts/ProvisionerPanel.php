<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ProvisionerPanelData;
use App\Agovena\Provisioning\ServiceInstanceInfo;

/**
 * Provisioning Extensions implement this interface only when they expose safe display fields.
 */
interface ProvisionerPanel
{
    public function panel(ServiceInstanceInfo $instance): ?ProvisionerPanelData;
}

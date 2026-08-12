<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

/**
 * Provider seam for provisioning Extensions.
 * Core and the Provisioning Module must not hardcode vendor SDKs.
 * Extensions optionally implement ProvisionerActions and ProvisionerPanel.
 */
interface Provisioner
{
    public function id(): string;

    public function label(): string;
}

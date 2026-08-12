<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

/**
 * Future provider seam for provisioning Extensions (Pterodactyl, Proxmox, webhooks, …).
 * Core and the Provisioning Module must not hardcode vendor SDKs.
 */
interface Provisioner
{
    public function id(): string;

    public function label(): string;
}

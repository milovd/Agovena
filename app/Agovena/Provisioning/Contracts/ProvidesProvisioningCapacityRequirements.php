<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

interface ProvidesProvisioningCapacityRequirements
{
    /** @param array<string, mixed> $providerSettings */
    /** @param array<string, mixed>|null $serverSettings */
    /** @return array<string, int|string> */
    public function capacityRequirements(array $providerSettings, ?array $serverSettings = null): array;
}

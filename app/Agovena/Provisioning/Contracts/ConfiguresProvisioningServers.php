<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Payments\HealthResult;

interface ConfiguresProvisioningServers
{
    /** @return list<ExtensionSettingDefinition> */
    public function serverSettings(): array;

    /** @param array<string, mixed> $settings */
    public function testServer(array $settings): HealthResult;
}

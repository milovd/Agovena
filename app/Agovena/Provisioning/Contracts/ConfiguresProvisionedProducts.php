<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Extensions\ExtensionSettingDefinition;

/**
 * Optional Provisioner capability. Product Admin renders these fields when this
 * provisioner is selected. Values are stored in capability config.provider_settings.
 */
interface ConfiguresProvisionedProducts
{
    /**
     * @return list<ExtensionSettingDefinition>
     */
    public function productSettings(): array;
}

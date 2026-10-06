<?php

declare(strict_types=1);

namespace Agovena\Extensions\ExperimentalProvisioner;

use App\Agovena\Extensions\Contracts\Extension;
use App\Agovena\Extensions\ExtensionContext;

final class ExperimentalProvisionerExtension implements Extension
{
    public function id(): string
    {
        return 'experimental-provisioner';
    }

    public function register(ExtensionContext $context): void {}
}

<?php

declare(strict_types=1);

namespace Agovena\Extensions\SampleGateway;

use App\Agovena\Extensions\Contracts\Extension;
use App\Agovena\Extensions\ExtensionContext;

final class SampleGatewayExtension implements Extension
{
    public function id(): string
    {
        return 'sample-gateway';
    }

    public function register(ExtensionContext $context): void {}
}

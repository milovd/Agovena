<?php

declare(strict_types=1);

namespace Agovena\Modules\Sample;

use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;

final class SampleModule implements Module
{
    public function id(): string
    {
        return 'sample';
    }

    public function register(ModuleContext $context): void
    {
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'sample',
            label: 'Sample',
            providedByModule: $this->id(),
        ));
    }
}

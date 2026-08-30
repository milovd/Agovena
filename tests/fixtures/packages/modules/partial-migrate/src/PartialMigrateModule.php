<?php

declare(strict_types=1);

namespace Agovena\Modules\PartialMigrate;

use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;

final class PartialMigrateModule implements Module
{
    public function register(ModuleContext $context): void
    {
        unset($context);
    }
}

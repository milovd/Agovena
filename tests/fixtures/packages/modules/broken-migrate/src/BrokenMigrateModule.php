<?php

declare(strict_types=1);

namespace Agovena\Modules\BrokenMigrate;

use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;

final class BrokenMigrateModule implements Module
{
    public function register(ModuleContext $context): void
    {
        // Intentionally empty — used only for migration failure coverage.
    }
}

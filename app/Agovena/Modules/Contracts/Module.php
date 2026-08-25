<?php

declare(strict_types=1);

namespace App\Agovena\Modules\Contracts;

use App\Agovena\Modules\ModuleContext;

/**
 * Public Module entrypoint. Modules must not require Livewire/BEM knowledge here - * Admin UI is an optional implementation detail registered via Agovena contracts.
 */
interface Module
{
    public function id(): string;

    public function register(ModuleContext $context): void;
}

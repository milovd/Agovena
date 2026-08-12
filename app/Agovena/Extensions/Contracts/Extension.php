<?php

declare(strict_types=1);

namespace App\Agovena\Extensions\Contracts;

use App\Agovena\Extensions\ExtensionContext;

/**
 * Public Extension entrypoint. Extensions must not require Livewire/BEM knowledge —
 * Admin UI is an optional implementation detail registered via Agovena contracts.
 */
interface Extension
{
    public function id(): string;

    public function register(ExtensionContext $context): void;
}

<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

/**
 * Module-registered customer account navigation entry.
 * Themes render these without importing Module classes.
 */
final class AccountNavItem
{
    /**
     * @param  (callable(): bool)|null  $visible
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $route,
        public readonly string $section,
        public readonly int $sort = 50,
        public readonly mixed $visible = null,
    ) {}
}

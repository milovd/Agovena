<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

/**
 * Module-registered customer account navigation entry.
 * Themes render these without importing Module classes.
 */
final class AccountNavItem
{
    public const GROUP_PRIMARY = 'primary';

    public const GROUP_PURCHASES = 'purchases';

    public const GROUP_SERVICES = 'services';

    public const GROUP_ACCOUNT = 'account';

    /**
     * @param  (callable(): bool)|null  $visible
     * @param  self::GROUP_*  $group
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $route,
        public readonly string $section,
        public readonly int $sort = 50,
        public readonly mixed $visible = null,
        public readonly ?string $icon = null,
        public readonly string $group = self::GROUP_PRIMARY,
    ) {}
}

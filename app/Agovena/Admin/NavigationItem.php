<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final class NavigationItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $group,
        public readonly ?string $href = null,
        public readonly ?string $icon = null,
        public readonly ?string $permission = null,
        public readonly int $sort = 0,
        public readonly ?string $parent = null,
        public readonly ?string $moduleId = null,
    ) {}
}

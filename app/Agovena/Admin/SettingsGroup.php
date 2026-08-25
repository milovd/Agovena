<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final readonly class SettingsGroup
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $permission = null,
        public int $sort = 0,
        public ?string $description = null,
        public ?string $icon = null,
        /**
         * Optional custom Admin href (external from the tabbed settings hub).
         * When null, the group is edited as a Settings Hub tab.
         */
        public ?string $href = null,
    ) {}

    public function resolveHref(): string
    {
        return $this->href ?? '/admin/settings/'.$this->id;
    }
}

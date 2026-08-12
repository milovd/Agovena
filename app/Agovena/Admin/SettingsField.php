<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final readonly class SettingsField
{
    /**
     * @param  list<string>|array<string, string>|null  $options  List of values, or value => label map
     */
    public function __construct(
        public string $group,
        public string $key,
        public string $label,
        public string $type,
        public mixed $default = null,
        public ?string $help = null,
        public ?string $permission = null,
        public int $sort = 0,
        public ?array $options = null,
    ) {}
}

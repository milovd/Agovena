<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final readonly class SettingsField
{
    /**
     * @param  list<string>|array<string, string>|null  $options  List of values, or value => label map
     * @param  int|null  $min  Lower bound for integer and percentage fields (defaults to 0)
     * @param  int|null  $max  Upper bound for integer (defaults to 365) and percentage (defaults to 100) fields
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
        public ?int $min = null,
        public ?int $max = null,
    ) {}
}

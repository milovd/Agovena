<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

final class ThemeSettingField
{
    /**
     * @param  list<string|int|float>|null  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly mixed $default = null,
        public readonly string $group = 'general',
        public readonly ?string $help = null,
        public readonly ?array $options = null,
        public readonly int $sort = 100,
    ) {}
}

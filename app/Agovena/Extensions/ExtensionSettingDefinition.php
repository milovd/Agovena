<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

/**
 * Declares an Extension setting field shown in Admin.
 */
final readonly class ExtensionSettingDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public bool $secret = false,
        public bool $required = false,
        public mixed $default = null,
        public string $help = '',
    ) {}
}

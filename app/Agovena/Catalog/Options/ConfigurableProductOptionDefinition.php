<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

use App\Enums\ProductOptionType;

final readonly class ConfigurableProductOptionDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public ProductOptionType $type,
        public bool $dynamicChoices = false,
        public ?string $help = null,
        public bool $lockType = false,
        public bool $sensitive = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

final readonly class ConfigurableProductOptionContext
{
    /**
     * @param  array<string, mixed>  $productSettings
     * @param  array<string, mixed>|null  $serverSettings
     * @param  array<string, mixed>  $selections
     */
    public function __construct(
        public array $productSettings,
        public ?array $serverSettings,
        public array $selections,
    ) {}
}

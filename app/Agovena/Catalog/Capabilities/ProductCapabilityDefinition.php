<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Capabilities;

/**
 * Declares a product capability key that Modules (or Core) may attach to products.
 * Config lives on product_capabilities.config and/or module-owned tables - not fat product columns.
 */
final class ProductCapabilityDefinition
{
    /**
     * @param  list<string>  $requires  Other capability keys that should also be present
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description = '',
        public readonly array $requires = [],
        public readonly ?string $providedByModule = null,
    ) {}
}

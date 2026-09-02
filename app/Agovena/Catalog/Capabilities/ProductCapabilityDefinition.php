<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Capabilities;

use Closure;

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
        /** @var (Closure(array<string, mixed>): bool)|null */
        public readonly ?Closure $availability = null,
    ) {}

    /**
     * Provider-backed capabilities may become unavailable when their extension
     * is disabled while the owning module remains enabled.
     *
     * @param  array<string, mixed>  $config
     */
    public function isAvailable(array $config): bool
    {
        return $this->availability === null || ($this->availability)($config);
    }
}

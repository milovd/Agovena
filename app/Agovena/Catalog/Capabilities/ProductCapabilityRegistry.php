<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Capabilities;

final class ProductCapabilityRegistry
{
    /** @var array<string, ProductCapabilityDefinition> */
    private array $definitions = [];

    public function register(ProductCapabilityDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function get(string $key): ?ProductCapabilityDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * @return list<ProductCapabilityDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * Capabilities currently available (registered by Core or enabled Modules).
     *
     * @return list<ProductCapabilityDefinition>
     */
    public function available(): array
    {
        return $this->all();
    }
}

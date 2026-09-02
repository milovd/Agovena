<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Capabilities;

use App\Agovena\Modules\ModuleManager;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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
        $definition = $this->definitions[$key] ?? null;

        return $definition !== null && $this->isModuleAvailable($definition)
            ? $definition
            : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
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
        return array_values(array_filter(
            $this->definitions,
            fn (ProductCapabilityDefinition $definition): bool => $this->isModuleAvailable($definition),
        ));
    }

    /** @return list<string> */
    public function availableKeys(): array
    {
        return array_map(
            static fn (ProductCapabilityDefinition $definition): string => $definition->key,
            $this->available(),
        );
    }

    public function productIsAvailable(Product $product): bool
    {
        $product->loadMissing('capabilities');

        foreach ($product->capabilities as $capability) {
            $definition = $this->get($capability->capability);
            if ($definition === null || $capability->hasCorruptConfig()) {
                return false;
            }

            try {
                if (! $definition->isAvailable($capability->runtimeConfig() ?? [])) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exclude products carrying a capability that is not currently registered.
     * Provider-specific config availability is checked after hydration.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function constrainToAvailable(Builder $query): Builder
    {
        $availableKeys = $this->availableKeys();

        if ($availableKeys === []) {
            return $query->whereDoesntHave('capabilities');
        }

        return $query->whereDoesntHave(
            'capabilities',
            static fn (Builder $capabilities): Builder => $capabilities->whereNotIn('capability', $availableKeys),
        );
    }

    private function isModuleAvailable(ProductCapabilityDefinition $definition): bool
    {
        return $definition->providedByModule === null
            || app(ModuleManager::class)->isEnabled($definition->providedByModule);
    }
}

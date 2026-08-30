<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use App\Agovena\Cart\PricedCartLine;
use App\Models\Product;

/**
 * Immutable input for a provider-owned checkout capacity check.
 *
 * Server settings are null when the product uses the provider's global
 * connection. They are never exposed to the storefront.
 */
final readonly class ProvisioningStockContext
{
    /**
     * @param  array<string, mixed>  $providerSettings
     * @param  array<string, mixed>|null  $serverSettings
     */
    public function __construct(
        public Product $product,
        public PricedCartLine $line,
        public string $providerKey,
        public array $providerSettings,
        public ?array $serverSettings = null,
        public ?int $serverId = null,
        public ?int $quantityOverride = null,
        public bool $serverSettingsRequired = false,
    ) {}

    public function quantity(): int
    {
        return max(1, $this->quantityOverride ?? $this->line->quantity);
    }
}

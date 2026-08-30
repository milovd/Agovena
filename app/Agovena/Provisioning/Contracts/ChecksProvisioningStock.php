<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Provisioning\ProvisioningStockContext;

/**
 * Provider-owned, fail-closed capacity validation for checkout.
 *
 * Implementations must check the provider's current capacity and may define
 * the scope in which local checkout reservations are serialized.
 */
interface ChecksProvisioningStock
{
    public function capacityKey(ProvisioningStockContext $context): string;

    /**
     * Recreate the reservation scope when a paid order becomes a service instance.
     *
     * @param  array<string, mixed>  $providerSettings
     */
    public function capacityKeyForSettings(
        array $providerSettings,
        ?int $serverId = null,
        ?array $serverSettings = null,
    ): string;

    /**
     * @param  int  $reservedQuantity  Quantity held by other unprovisioned orders.
     */
    public function assertStock(ProvisioningStockContext $context, int $reservedQuantity = 0): void;
}

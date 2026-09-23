<?php

declare(strict_types=1);

namespace App\Agovena\Availability;

use App\Agovena\Availability\Contracts\ProvidesCapacity;
use App\Models\Product;

final class AvailabilityContract
{
    public function __construct(private readonly CapacityProviderRegistry $providers) {}

    public function canFulfil(Product $product, int $quantity): bool
    {
        $stock = Models\InventoryStock::query()->where('product_id', $product->id)->first();
        if ($stock === null) {
            return false;
        }

        return match ($stock->availability_mode) {
            AvailabilityMode::Unlimited => true,
            AvailabilityMode::Finite => $stock->allow_oversell || $stock->quantity >= $quantity,
            AvailabilityMode::ProviderCapacity => $stock->provider_key !== null
                && ($provider = $this->providers->get($stock->provider_key)) instanceof ProvidesCapacity
                && $provider->canFulfil($product, $quantity),
        };
    }
}

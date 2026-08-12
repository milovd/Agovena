<?php

declare(strict_types=1);

namespace App\Agovena\Shipping;

use App\Agovena\Shipping\Contracts\ShippingCarrier;

final class ShippingCarrierRegistry
{
    /** @var array<string, ShippingCarrier> */
    private array $items = [];

    public function register(ShippingCarrier $carrier): void
    {
        $this->items[$carrier->id()] = $carrier;
    }

    public function get(string $id): ?ShippingCarrier
    {
        return $this->items[$id] ?? null;
    }

    /** @return list<ShippingCarrier> */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}

<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

final readonly class CartRequirements
{
    /**
     * @param  list<CartRequirement>  $items
     */
    public function __construct(public array $items) {}

    public function has(CartRequirement $requirement): bool
    {
        return in_array($requirement, $this->items, true);
    }

    public function requiresShipping(): bool
    {
        return $this->has(CartRequirement::ShippingAddress)
            || $this->has(CartRequirement::ShippingMethod);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (CartRequirement $item): string => $item->value, $this->items);
    }
}

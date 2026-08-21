<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\CartService;

final class CartRequirementComposer
{
    /**
     * @param  list<CartRequirementContributor>  $contributors
     */
    public function __construct(private array $contributors = []) {}

    public function add(CartRequirementContributor $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    public function compose(CartService $cart): CartRequirements
    {
        $seen = [];
        $items = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->contribute($cart) as $requirement) {
                if (isset($seen[$requirement->value])) {
                    continue;
                }
                $seen[$requirement->value] = true;
                $items[] = $requirement;
            }
        }

        return new CartRequirements($items);
    }
}

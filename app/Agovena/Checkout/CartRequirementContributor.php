<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\CartService;

interface CartRequirementContributor
{
    /**
     * @return list<CartRequirement>
     */
    public function contribute(CartService $cart): array;
}

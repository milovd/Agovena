<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\Contributors;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementContributor;

final class CoreCheckoutContributor implements CartRequirementContributor
{
    public function contribute(CartService $cart): array
    {
        return [
            CartRequirement::Billing,
            CartRequirement::Payment,
            CartRequirement::Review,
        ];
    }
}

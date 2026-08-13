<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\Contributors;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementContributor;
use App\Agovena\Customer\Properties\CustomerPropertyService;

final class CustomPropertyRequirementContributor implements CartRequirementContributor
{
    public function __construct(
        private readonly CustomerPropertyService $properties,
    ) {}

    public function contribute(CartService $cart): array
    {
        if ($this->properties->definitionsFor('checkout')->isEmpty()) {
            return [];
        }

        return [CartRequirement::CustomProperties];
    }
}

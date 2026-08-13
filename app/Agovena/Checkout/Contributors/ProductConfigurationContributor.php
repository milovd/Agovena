<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\Contributors;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementContributor;
use App\Models\Product;

final class ProductConfigurationContributor implements CartRequirementContributor
{
    public function __construct(
        private readonly ProductOptionValidator $options,
    ) {}

    public function contribute(CartService $cart): array
    {
        foreach ($cart->lines() as $line) {
            $product = Product::query()->find($line->productId);
            if ($product === null) {
                continue;
            }
            if ($this->options->activeOptions($product)->isNotEmpty()) {
                return [CartRequirement::ProductConfiguration];
            }
        }

        return [];
    }
}

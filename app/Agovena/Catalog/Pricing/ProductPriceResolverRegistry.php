<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Pricing;

use App\Agovena\Catalog\Pricing\Contracts\ProductPriceResolver;
use App\Agovena\Money\Money;
use App\Models\Product;
use InvalidArgumentException;

final class ProductPriceResolverRegistry
{
    /** @var array<string, ProductPriceResolver> */
    private array $items = [];

    public function register(ProductPriceResolver $resolver): void
    {
        $this->items[$resolver->id()] = $resolver;
    }

    /**
     * @param  array<string, mixed>  $selections
     */
    public function resolve(Product $product, array $selections, string $currency): ?Money
    {
        $currency = strtoupper($currency);

        foreach ($this->items as $resolver) {
            if (! $resolver->supports($product)) {
                continue;
            }

            $resolved = $resolver->resolve($product, $selections, $currency);
            if (strtoupper($resolved->currency) !== $currency) {
                throw new InvalidArgumentException('Product price resolver returned an unexpected currency.');
            }

            return $resolved;
        }

        return null;
    }
}

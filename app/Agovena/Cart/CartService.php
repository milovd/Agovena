<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Media\ProductMedia;
use App\Agovena\Money\Money;
use App\Agovena\Money\ResolveProductPrice;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CartService
{
    public function __construct(
        private readonly CartRepository $cart,
        private readonly ProductCapabilityRegistry $capabilities,
        private readonly ProductOptionValidator $optionValidator,
        private readonly ProductOptionPricer $optionPricer,
        private readonly ResolveProductPrice $resolveProductPrice,
    ) {}

    /**
     * @param  array<string, mixed>  $selections
     */
    public function add(int $productId, int $quantity = 1, array $selections = []): void
    {
        $product = $this->requirePurchasable($productId);
        $clean = $this->optionValidator->validate($product, $selections);
        $this->cart->add($product->id, $quantity, $clean);
    }

    public function update(int|string $productIdOrLineKey, int $quantity): void
    {
        $lineKey = (string) $productIdOrLineKey;
        if ($quantity > 0) {
            $line = $this->lineByKey($lineKey);
            if ($line !== null) {
                $this->requirePurchasable($line->productId);
            }
        }

        $this->cart->update($lineKey, $quantity);
    }

    public function remove(int|string $productIdOrLineKey): void
    {
        $this->cart->remove((string) $productIdOrLineKey);
    }

    public function clear(): void
    {
        $this->cart->clear();
    }

    /**
     * @return list<CartLine>
     */
    public function lines(): array
    {
        $this->removeUnavailable();

        return $this->cart->lines();
    }

    /**
     * Drop lines whose products were deleted or are no longer purchasable.
     *
     * @return list<int> Removed product ids
     */
    public function removeUnavailable(): array
    {
        $removed = [];

        foreach ($this->cart->lines() as $line) {
            $product = Product::query()->with(['currencyPrices', 'capabilities'])->find($line->productId);

            if (
                $product === null
                || ! $product->isPurchasable()
                || ! $this->capabilities->productIsAvailable($product)
                || ! $this->resolveProductPrice->isAvailable($product)
            ) {
                $this->cart->remove($line->lineKey);
                $removed[] = $line->productId;
            }
        }

        return $removed;
    }

    /**
     * Server-authoritative pricing. Client-submitted prices are never used.
     *
     * @return list<PricedCartLine>
     */
    public function pricedLines(): array
    {
        $this->removeUnavailable();

        $priced = [];

        foreach ($this->cart->lines() as $line) {
            $product = Product::query()->with(['images', 'currencyPrices', 'capabilities'])->find($line->productId);

            if (
                $product === null
                || ! $product->isPurchasable()
                || ! $this->capabilities->productIsAvailable($product)
                || ! $this->resolveProductPrice->isAvailable($product)
            ) {
                continue;
            }

            try {
                $unit = $this->optionPricer->unitPrice($product, $line->selections);
            } catch (InvalidArgumentException) {
                continue;
            }
            $snapshot = $this->optionPricer->snapshot($product, $line->selections);
            $optionLabels = [];
            foreach ($snapshot as $row) {
                $optionLabels[] = [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'display' => $row['display'],
                ];
            }

            $priced[] = new PricedCartLine(
                productId: $product->id,
                label: $product->name,
                quantity: $line->quantity,
                unitPrice: $unit,
                lineTotal: $unit->multiply($line->quantity),
                lineKey: $line->lineKey,
                selections: $line->selections,
                optionLabels: $optionLabels,
                slug: $product->slug,
                imageUrl: ProductMedia::primaryUrl($product),
            );
        }

        return $priced;
    }

    public function subtotal(): ?Money
    {
        $lines = $this->pricedLines();

        if ($lines === []) {
            return null;
        }

        $total = Money::of(0, $lines[0]->unitPrice->currency);

        foreach ($lines as $line) {
            $total = $total->add($line->lineTotal);
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        $this->removeUnavailable();

        return $this->cart->lines() === [];
    }

    public function itemCount(): int
    {
        $this->removeUnavailable();

        $count = 0;

        foreach ($this->cart->lines() as $line) {
            $count += $line->quantity;
        }

        return $count;
    }

    /**
     * True when a shippable capability is registered and present on any cart line.
     * Digital/service-only carts stay false so checkout does not collect a shipping address.
     */
    public function requiresShipping(): bool
    {
        if (! $this->capabilities->has('shippable')) {
            return false;
        }

        $this->removeUnavailable();

        foreach ($this->cart->lines() as $line) {
            $product = Product::query()->with('capabilities')->find($line->productId);
            if ($product !== null && $product->hasCapability('shippable')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cart lines whose products carry the shippable capability (mixed carts supported).
     *
     * @return list<PricedCartLine>
     */
    public function shippableLines(): array
    {
        if (! $this->capabilities->has('shippable')) {
            return [];
        }

        $shippable = [];
        foreach ($this->pricedLines() as $line) {
            $product = Product::query()->with('capabilities')->find($line->productId);
            if ($product !== null && $product->hasCapability('shippable')) {
                $shippable[] = $line;
            }
        }

        return $shippable;
    }

    public function assertConfigured(): void
    {
        foreach ($this->cart->lines() as $line) {
            $product = Product::query()->find($line->productId);
            if ($product === null) {
                continue;
            }
            $this->optionValidator->validate($product, $line->selections);
        }
    }

    private function lineByKey(string $lineKey): ?CartLine
    {
        foreach ($this->cart->lines() as $line) {
            if ($line->lineKey === $lineKey) {
                return $line;
            }
        }

        return null;
    }

    private function requirePurchasable(int $productId): Product
    {
        $product = Product::query()->with(['currencyPrices', 'capabilities'])->find($productId);

        if ($product === null || ! $product->isPurchasable() || ! $this->capabilities->productIsAvailable($product)) {
            throw ValidationException::withMessages([
                'product' => __('storefront.errors.product_unavailable'),
            ]);
        }

        if (! $this->resolveProductPrice->isAvailable($product)) {
            throw ValidationException::withMessages([
                'product' => __('storefront.errors.product_currency_unavailable'),
            ]);
        }

        return $product;
    }
}

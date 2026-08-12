<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Money\Money;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class CartService
{
    public function __construct(private readonly CartRepository $cart) {}

    public function add(int $productId, int $quantity = 1): void
    {
        $product = $this->requirePurchasable($productId);
        $this->cart->add($product->id, $quantity);
    }

    public function update(int $productId, int $quantity): void
    {
        if ($quantity > 0) {
            $this->requirePurchasable($productId);
        }

        $this->cart->update($productId, $quantity);
    }

    public function remove(int $productId): void
    {
        $this->cart->remove($productId);
    }

    public function clear(): void
    {
        $this->cart->clear();
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
            $product = Product::query()->find($line->productId);

            if ($product === null || ! $product->isPurchasable()) {
                $this->cart->remove($line->productId);
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
            $product = Product::query()->find($line->productId);

            if ($product === null || ! $product->isPurchasable()) {
                continue;
            }

            $unit = $product->money();
            $priced[] = new PricedCartLine(
                productId: $product->id,
                label: $product->name,
                quantity: $line->quantity,
                unitPrice: $unit,
                lineTotal: $unit->multiply($line->quantity),
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

    private function requirePurchasable(int $productId): Product
    {
        $product = Product::query()->find($productId);

        if ($product === null || ! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product' => __('storefront.errors.product_unavailable'),
            ]);
        }

        return $product;
    }
}

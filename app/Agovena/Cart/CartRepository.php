<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

interface CartRepository
{
    /** @return list<CartLine> */
    public function lines(): array;

    public function add(int $productId, int $quantity = 1): void;

    public function update(int $productId, int $quantity): void;

    public function remove(int $productId): void;

    public function clear(): void;
}

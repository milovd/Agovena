<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

interface CartRepository
{
    /** @return list<CartLine> */
    public function lines(): array;

    /** @param array<string, mixed> $selections */
    public function add(int $productId, int $quantity = 1, array $selections = []): void;

    public function update(string $lineKey, int $quantity): void;

    public function remove(string $lineKey): void;

    public function clear(): void;
}

<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use Illuminate\Contracts\Session\Session;

final class SessionCartRepository implements CartRepository
{
    private const string KEY = 'agovena.cart';

    public function __construct(private readonly Session $session) {}

    public function lines(): array
    {
        $lines = [];

        foreach ($this->raw() as $productId => $quantity) {
            $qty = (int) $quantity;
            if ($qty > 0) {
                $lines[] = new CartLine((int) $productId, $qty);
            }
        }

        return $lines;
    }

    public function add(int $productId, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        $items = $this->raw();
        $current = array_key_exists($productId, $items) ? $items[$productId] : 0;
        $items[$productId] = $current + $quantity;
        $this->session->put(self::KEY, $items);
    }

    public function update(int $productId, int $quantity): void
    {
        $items = $this->raw();

        if ($quantity < 1) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $quantity;
        }

        $this->session->put(self::KEY, $items);
    }

    public function remove(int $productId): void
    {
        $items = $this->raw();
        unset($items[$productId]);
        $this->session->put(self::KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /** @return array<int, int> */
    private function raw(): array
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $productId => $quantity) {
            $items[(int) $productId] = (int) $quantity;
        }

        return $items;
    }
}

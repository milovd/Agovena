<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Catalog\Options\CartLineKey;
use Illuminate\Contracts\Session\Session;

final class SessionCartRepository implements CartRepository
{
    private const string KEY = 'agovena.cart';

    public function __construct(private readonly Session $session) {}

    public function lines(): array
    {
        $lines = [];

        foreach ($this->raw() as $row) {
            $qty = (int) $row['quantity'];
            if ($qty < 1) {
                continue;
            }
            $productId = (int) $row['product_id'];
            if ($productId < 1) {
                continue;
            }
            $selections = $row['selections'];
            $lines[] = new CartLine($productId, $qty, CartLineKey::normalize($selections));
        }

        return $lines;
    }

    public function add(int $productId, int $quantity = 1, array $selections = []): void
    {
        if ($quantity < 1) {
            return;
        }

        $normalized = CartLineKey::normalize($selections);
        $lineKey = CartLineKey::make($productId, $normalized);
        $items = $this->raw();
        $found = false;

        foreach ($items as $index => $row) {
            $existing = new CartLine(
                (int) $row['product_id'],
                (int) $row['quantity'],
                $row['selections'],
            );
            if ($existing->lineKey === $lineKey) {
                $items[$index]['quantity'] = (int) $row['quantity'] + $quantity;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'selections' => $normalized,
            ];
        }

        $this->persist($items);
    }

    public function update(string $lineKey, int $quantity): void
    {
        $items = $this->raw();

        foreach ($items as $index => $row) {
            $existing = new CartLine(
                (int) $row['product_id'],
                (int) $row['quantity'],
                $row['selections'],
            );
            if ($existing->lineKey !== $lineKey) {
                continue;
            }
            if ($quantity < 1) {
                unset($items[$index]);
            } else {
                $items[$index]['quantity'] = $quantity;
            }
            break;
        }

        $this->persist($items);
    }

    public function remove(string $lineKey): void
    {
        $this->update($lineKey, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @param  list<array{product_id: int, quantity: int, selections: array<string, mixed>}>  $items
     */
    private function persist(array $items): void
    {
        $lines = [];
        foreach ($items as $row) {
            $lines[] = $row;
        }

        if ($lines === []) {
            $this->session->forget(self::KEY);

            return;
        }

        $this->session->put(self::KEY, ['v' => 2, 'lines' => $lines]);
    }

    /**
     * @return list<array{product_id: int, quantity: int, selections: array<string, mixed>}>
     */
    private function raw(): array
    {
        /** @var mixed $raw */
        $raw = $this->session->get(self::KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        if (($raw['v'] ?? null) === 2 && isset($raw['lines']) && is_array($raw['lines'])) {
            $lines = [];
            foreach ($raw['lines'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lines[] = [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'selections' => isset($row['selections']) && is_array($row['selections']) ? $row['selections'] : [],
                ];
            }

            return $lines;
        }

        $legacy = [];
        foreach ($raw as $productId => $quantity) {
            if (is_array($quantity)) {
                continue;
            }
            $legacy[] = [
                'product_id' => (int) $productId,
                'quantity' => (int) $quantity,
                'selections' => [],
            ];
        }

        return $legacy;
    }
}

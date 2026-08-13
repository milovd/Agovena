<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use App\Agovena\Catalog\Options\CartLineKey;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;

final class TokenCartRepository implements CartRepository
{
    public function __construct(
        private readonly Request $request,
        private readonly Repository $cache,
    ) {}

    public function token(): string
    {
        return $this->cacheToken();
    }

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
            $lines[] = new CartLine($productId, $qty, CartLineKey::normalize($row['selections']));
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
            $existing = new CartLine((int) $row['product_id'], (int) $row['quantity'], $row['selections']);
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
            $existing = new CartLine((int) $row['product_id'], (int) $row['quantity'], $row['selections']);
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
        $this->cache->forget($this->cacheKey());
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, selections: array<string, mixed>}>  $items
     */
    private function persist(array $items): void
    {
        $lines = array_values($items);

        if ($lines === []) {
            $this->cache->forget($this->cacheKey());

            return;
        }

        $this->cache->put($this->cacheKey(), ['v' => 2, 'lines' => $lines], now()->addDays(7));
    }

    /**
     * @return list<array{product_id: int, quantity: int, selections: array<string, mixed>}>
     */
    private function raw(): array
    {
        $raw = $this->cache->get($this->cacheKey(), []);
        if (! is_array($raw) || ($raw['v'] ?? null) !== 2 || ! isset($raw['lines']) || ! is_array($raw['lines'])) {
            return [];
        }

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

    private function cacheKey(): string
    {
        $user = $this->request->user();
        if ($user instanceof User) {
            return 'agovena.api-cart.customer.'.$user->id;
        }

        return 'agovena.api-cart.token.'.$this->cacheToken();
    }

    private function cacheToken(): string
    {
        $existing = $this->request->attributes->get('api_cart_token');
        if (is_string($existing) && preg_match('/^[a-f0-9]{64}$/', $existing) === 1) {
            return $existing;
        }

        $header = trim((string) $this->request->header('X-Cart-Token', ''));
        if (preg_match('/^[a-f0-9]{64}$/', $header) === 1) {
            $this->request->attributes->set('api_cart_token', $header);

            return $header;
        }

        $token = bin2hex(random_bytes(32));
        $this->request->attributes->set('api_cart_token', $token);

        return $token;
    }
}

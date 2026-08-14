<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class CartPage extends Component
{
    /** @var array<string, int> */
    public array $quantities = [];

    public function mount(CartService $cart): void
    {
        $removed = $cart->removeUnavailable();
        if ($removed !== []) {
            session()->flash('status', __('storefront.flash.unavailable_removed'));
        }

        $this->refreshQuantities($cart);
    }

    public function incrementLine(string $lineKey, CartService $cart): void
    {
        $qty = min(99, ((int) ($this->quantities[$lineKey] ?? 1)) + 1);
        $cart->update($lineKey, $qty);
        $this->refreshQuantities($cart);
    }

    public function decrementLine(string $lineKey, CartService $cart): void
    {
        $qty = (int) ($this->quantities[$lineKey] ?? 1);
        if ($qty <= 1) {
            return;
        }

        $cart->update($lineKey, $qty - 1);
        $this->refreshQuantities($cart);
    }

    public function updateLine(string $lineKey, CartService $cart): void
    {
        $qty = max(1, min(99, (int) ($this->quantities[$lineKey] ?? 1)));
        $cart->update($lineKey, $qty);
        $this->refreshQuantities($cart);
    }

    public function removeLine(string $lineKey, CartService $cart): void
    {
        $cart->remove($lineKey);
        unset($this->quantities[$lineKey]);
    }

    public function render(CartService $cart, ThemeManager $themes)
    {
        $theme = $themes->active();
        $lines = $cart->pricedLines();
        $subtotal = $cart->subtotal();

        return view($theme->view('cart.index'), [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.cart.title'),
            'theme' => $theme,
        ]);
    }

    private function refreshQuantities(CartService $cart): void
    {
        $this->quantities = [];
        foreach ($cart->pricedLines() as $line) {
            $this->quantities[$line->lineKey] = $line->quantity;
        }
    }
}

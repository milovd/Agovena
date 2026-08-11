<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class CartPage extends Component
{
    /** @var array<int, int> */
    public array $quantities = [];

    public function mount(CartService $cart): void
    {
        try {
            foreach ($cart->pricedLines() as $line) {
                $this->quantities[$line->productId] = $line->quantity;
            }
        } catch (ValidationException) {
            $this->quantities = [];
        }
    }

    public function updateLine(int $productId, CartService $cart): void
    {
        $qty = (int) ($this->quantities[$productId] ?? 0);
        $cart->update($productId, $qty);
        $this->refreshQuantities($cart);
    }

    public function removeLine(int $productId, CartService $cart): void
    {
        $cart->remove($productId);
        unset($this->quantities[$productId]);
    }

    public function render(CartService $cart, ThemeManager $themes)
    {
        $theme = $themes->active();

        try {
            $lines = $cart->pricedLines();
            $subtotal = $cart->subtotal();
        } catch (ValidationException $e) {
            $lines = [];
            $subtotal = null;
            session()->flash('error', $e->getMessage());
        }

        return view($theme->view('cart.index'), [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => 'Cart',
            'theme' => $theme,
        ]);
    }

    private function refreshQuantities(CartService $cart): void
    {
        $this->quantities = [];
        foreach ($cart->pricedLines() as $line) {
            $this->quantities[$line->productId] = $line->quantity;
        }
    }
}

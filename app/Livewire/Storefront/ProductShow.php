<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class ProductShow extends Component
{
    public string $slug;

    public int $quantity = 1;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function addToCart(CartService $cart, GetStorefrontProduct $get): void
    {
        $product = $get->handle($this->slug);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart->add($product->id, $this->quantity);

        session()->flash('status', 'Added to cart.');

        $this->redirect(route('storefront.cart'), navigate: true);
    }

    public function render(GetStorefrontProduct $get, ThemeManager $themes)
    {
        $theme = $themes->active();
        $product = $get->handle($this->slug);

        return view($theme->view('catalog.show'), [
            'product' => $product,
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $product->name,
            'theme' => $theme,
        ]);
    }
}

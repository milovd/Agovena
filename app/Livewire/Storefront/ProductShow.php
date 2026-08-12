<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Settings\SettingsRepository;
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

    public function incrementQuantity(): void
    {
        $this->quantity = min(99, $this->quantity + 1);
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function addToCart(CartService $cart, GetStorefrontProduct $get): void
    {
        $this->addProduct($cart, $get);
        session()->flash('status', __('storefront.flash.added_to_cart'));
        $this->redirect(route('storefront.cart'), navigate: true);
    }

    public function buyNow(CartService $cart, GetStorefrontProduct $get): void
    {
        $this->addProduct($cart, $get);
        $this->redirect(route('storefront.checkout'), navigate: true);
    }

    public function render(GetStorefrontProduct $get, ListStorefrontProducts $list, ThemeManager $themes)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $product = $get->handle($this->slug);

        $related = $list->handle($product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->values();

        $enableReviews = filter_var(
            app(SettingsRepository::class)->get('store', 'enable_reviews', true),
            FILTER_VALIDATE_BOOLEAN
        );

        return view($theme->view('catalog.show'), [
            'product' => $product,
            'related' => $related,
            'theme' => $theme,
            'themeConfig' => $config,
            'enableReviews' => $enableReviews,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $product->name,
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }

    private function addProduct(CartService $cart, GetStorefrontProduct $get): void
    {
        $product = $get->handle($this->slug);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart->add($product->id, $this->quantity);
    }
}

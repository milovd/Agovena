<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class ProductConfigure extends Component
{
    public string $slug;

    public int $quantity = 1;

    /** @var array<string, mixed> */
    public array $optionSelections = [];

    public function mount(string $slug, GetStorefrontProduct $get, ProductOptionValidator $options): void
    {
        $this->slug = $slug;
        $this->quantity = min(99, max(1, (int) request()->query('quantity', 1)));

        $product = $get->handle($this->slug);
        foreach ($options->activeOptions($product) as $option) {
            $this->optionSelections[$option->key] = match ($option->type->value) {
                'checkbox' => [],
                'toggle' => false,
                default => '',
            };
        }
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

    public function render(
        GetStorefrontProduct $get,
        ProductOptionValidator $options,
        ProductOptionPricer $pricer,
        ThemeManager $themes,
    ) {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $product = $get->handle($this->slug);
        $purchaseOptions = $options->activeOptions($product);
        $configuredPrice = null;

        try {
            $configuredPrice = $pricer->unitPrice($product, $this->optionSelections);
        } catch (\InvalidArgumentException) {
            $configuredPrice = null;
        }

        return view($theme->view('catalog.configure'), [
            'product' => $product,
            'purchaseOptions' => $purchaseOptions,
            'configuredPrice' => $configuredPrice,
            'theme' => $theme,
            'themeConfig' => $config,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.product.configure_title', ['product' => $product->name]),
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

        $cart->add($product->id, $this->quantity, $this->optionSelections);
    }
}

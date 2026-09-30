<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Contracts\ProductStock;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Notifications\BackInStockNotifier;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Models\Product;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ProductShow extends Component
{
    public string $slug;

    public int $quantity = 1;

    public function incrementQuantity(): void
    {
        $this->quantity = min(99, $this->quantity + 1);
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public string $backInStockEmail = '';

    public string $backInStockMessage = '';

    public function subscribeToBackInStock(GetStorefrontProduct $get, BackInStockNotifier $notifier): void
    {
        $product = $get->handle($this->slug);
        $this->validate([
            'backInStockEmail' => ['required', 'email:rfc', 'max:255'],
        ]);

        $rateLimitKey = 'back-in-stock:'.request()->ip().':'.$product->getKey();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            throw ValidationException::withMessages([
                'backInStockEmail' => __('storefront.product.back_in_stock_rate_limited'),
            ]);
        }
        RateLimiter::hit($rateLimitKey, 60);

        $notifier->subscribe($product, $this->backInStockEmail);
        $this->backInStockEmail = '';
        $this->backInStockMessage = __('storefront.product.back_in_stock_subscribed');
    }

    public function addToCart(CartService $cart, GetStorefrontProduct $get, ProductOptionValidator $options): void
    {
        $product = $get->handle($this->slug);
        if ($this->redirectToConfiguration($product, $options, 'cart')) {
            return;
        }

        $this->addProduct($cart, $product);
        session()->flash('status', __('storefront.flash.added_to_cart'));
        $this->redirect(route('storefront.cart'), navigate: true);
    }

    public function buyNow(CartService $cart, GetStorefrontProduct $get, ProductOptionValidator $options): void
    {
        $product = $get->handle($this->slug);
        if ($this->redirectToConfiguration($product, $options, 'checkout')) {
            return;
        }

        $this->addProduct($cart, $product);
        $this->redirect(route('storefront.checkout'), navigate: true);
    }

    public function render(GetStorefrontProduct $get, ListStorefrontProducts $list, ThemeManager $themes, ProductOptionPricer $pricer)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $product = $get->handle($this->slug);

        $related = $list->handle(
            categoryId: $product->category_id,
            limit: 4,
            excludeId: $product->id,
        );

        $enableReviews = filter_var(
            app(SettingsRepository::class)->get('store', 'enable_reviews', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $configuredPrice = null;
        try {
            $configuredPrice = $pricer->unitPrice($product, []);
        } catch (\InvalidArgumentException) {
            $configuredPrice = null;
        }

        $isOutOfStock = app()->bound(ProductStock::class)
            && $product->hasCapability('inventory')
            && app(ProductStock::class)->quantityFor($product) < 1;

        return view($theme->view('catalog.show'), [
            'product' => $product,
            'related' => $related,
            'theme' => $theme,
            'themeConfig' => $config,
            'enableReviews' => $enableReviews,
            'configuredPrice' => $configuredPrice,
            'priceAvailable' => $configuredPrice !== null,
            'isOutOfStock' => $isOutOfStock,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $product->name,
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }

    private function addProduct(CartService $cart, Product $product): void
    {
        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart->add($product->id, $this->quantity);
    }

    private function redirectToConfiguration(Product $product, ProductOptionValidator $options, string $intent): bool
    {
        $requiresConfiguration = $product->hasCapability('domain_registration')
            || $options->activeOptions($product)->isNotEmpty();

        if (! $requiresConfiguration) {
            return false;
        }

        $route = $product->hasCapability('domain_registration')
            ? 'domains.product.configure'
            : 'storefront.product.configure';

        $parameters = ['slug' => $product->slug, 'intent' => $intent, 'quantity' => $this->quantity];
        $this->redirect(route($route, $parameters), navigate: true);

        return true;
    }
}

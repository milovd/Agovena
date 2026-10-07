<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Catalog\Options\ConfigurableProductOptionResolver;
use App\Agovena\Catalog\Options\ProductOptionChoicesUnavailable;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Theme\ThemeManager;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ProductConfigure extends Component
{
    public string $slug;

    #[Locked]
    public string $intent = 'cart';

    public int $quantity = 1;

    /** @var array<string, mixed> */
    public array $optionSelections = [];

    public function mount(string $slug, GetStorefrontProduct $get, ProductOptionValidator $options): void
    {
        $this->slug = $slug;
        $this->quantity = min(99, max(1, (int) request()->query('quantity', 1)));
        $this->intent = request()->query('intent') === 'checkout' ? 'checkout' : 'cart';

        $product = $get->handle($this->slug);
        if (! app(ProductCapabilityRegistry::class)->productIsAvailable($product)) {
            $this->redirect(route('storefront.product', ['slug' => $product->slug]), navigate: true);

            return;
        }

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

    public function continueConfiguration(CartService $cart, GetStorefrontProduct $get): void
    {
        $this->addProduct($cart, $get);

        if ($this->intent !== 'checkout') {
            session()->flash('status', __('storefront.flash.added_to_cart'));
        }

        $this->redirect(
            route($this->intent === 'checkout' ? 'storefront.checkout' : 'storefront.cart'),
            navigate: true,
        );
    }

    public function addToCart(CartService $cart, GetStorefrontProduct $get): void
    {
        $this->intent = 'cart';
        $this->continueConfiguration($cart, $get);
    }

    public function buyNow(CartService $cart, GetStorefrontProduct $get): void
    {
        $this->intent = 'checkout';
        $this->continueConfiguration($cart, $get);
    }

    public function render(
        GetStorefrontProduct $get,
        ProductOptionValidator $options,
        ProductOptionPricer $pricer,
        ConfigurableProductOptionResolver $configurableOptions,
        ThemeManager $themes,
    ) {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $product = $get->handle($this->slug);
        $purchaseOptions = $options->activeOptions($product);
        $dynamicOptionChoices = [];
        $optionHints = [];
        $optionChoiceErrors = [];
        foreach ($purchaseOptions as $option) {
            try {
                $choices = $configurableOptions->choices($product, $option, $this->optionSelections);
                if ($choices !== null) {
                    $dynamicOptionChoices[$option->key] = $choices;
                }
                $hint = $configurableOptions->hint($product, $option, $this->optionSelections);
                if ($hint !== null) {
                    $optionHints[$option->key] = $hint;
                }
            } catch (ProductOptionChoicesUnavailable) {
                $dynamicOptionChoices[$option->key] = [];
                $optionChoiceErrors[$option->key] = __('storefront.errors.product_option_choices_unavailable');
            }
        }
        $configuredPrice = null;

        try {
            $configuredPrice = $pricer->unitPrice($product, $this->optionSelections);
        } catch (\InvalidArgumentException) {
            $configuredPrice = null;
        }

        return view($theme->view('catalog.configure'), [
            'product' => $product,
            'purchaseOptions' => $purchaseOptions,
            'dynamicOptionChoices' => $dynamicOptionChoices,
            'optionHints' => $optionHints,
            'optionChoiceErrors' => $optionChoiceErrors,
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

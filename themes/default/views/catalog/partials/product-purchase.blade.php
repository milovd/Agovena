{{-- Price, quantity and buy actions, or the not-orderable state, plus back-in-stock signup (Livewire: ProductShow). --}}
@if (($priceAvailable || $isDomainProduct) && ! $isOutOfStock && $isOrderable)
<form wire:submit="addToCart" class="store-product__form">
    <div class="store-product__price-row">
        @if ($isDomainProduct)
            <p class="store-product__price store-product__price--dynamic">{{ __('storefront.product.domain_price_dynamic') }}</p>
        @elseif ($configuredPrice)
            <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($configuredPrice) }}</p>
        @else
            <p class="store-product__price store-product__price--unavailable">{{ __('storefront.product.not_available_in_currency') }}</p>
        @endif
    </div>

    <div class="store-product__quantity-row">
        <label class="visually-hidden" for="quantity">{{ __('storefront.product.quantity') }}</label>
        <div
            class="store-qty"
            role="group"
            aria-label="{{ __('storefront.product.quantity') }}"
            x-data="storefrontQuantity"
            data-min="1"
            data-max="99"
        >
            <button type="button" class="store-qty__btn" @click="decrement()" aria-label="{{ __('storefront.product.decrease') }}">−</button>
            <input id="quantity" class="store-qty__input" type="number" min="1" max="99" value="{{ $quantity }}" wire:model="quantity" x-ref="input" @change="normalize()">
            <button type="button" class="store-qty__btn" @click="increment()" aria-label="{{ __('storefront.product.increase') }}">+</button>
        </div>
    </div>

    <div class="store-product__actions">
        <button type="button" class="store-btn store-btn--primary store-btn--lg" wire:click="buyNow" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="buyNow">{{ __('storefront.product.buy_now') }}</span>
            <span wire:loading wire:target="buyNow">{{ __('storefront.product.working') }}</span>
        </button>
        <button type="submit" class="store-btn store-btn--outline store-btn--lg" wire:loading.attr="disabled" wire:target="addToCart">
            <span wire:loading.remove wire:target="addToCart">{{ __('storefront.product.add_to_cart') }}</span>
            <span wire:loading wire:target="addToCart">{{ __('storefront.product.adding') }}</span>
        </button>
    </div>
    @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
    @error('product') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
</form>
@else
    @if ($isDomainProduct)
        <p class="store-product__price store-product__price--dynamic">{{ __('storefront.product.domain_price_dynamic') }}</p>
    @elseif ($priceAvailable)
        <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($configuredPrice) }}</p>
    @else
        <p class="store-product__price store-product__price--unavailable">{{ __('storefront.product.not_available_in_currency') }}</p>
    @endif
    @if (! $isOrderable)
        <p class="store-product__availability" role="status">{{ __('storefront.product.unavailable_to_order') }}</p>
    @endif
@endif

@if ($isOutOfStock)
    <section class="store-product__back-in-stock" aria-labelledby="back-in-stock-heading">
        <h2 id="back-in-stock-heading">{{ __('storefront.product.back_in_stock_title') }}</h2>
        <p>{{ __('storefront.product.back_in_stock_text') }}</p>
        <form wire:submit="subscribeToBackInStock" class="store-product__back-in-stock-form">
            <label class="visually-hidden" for="backInStockEmail">{{ __('storefront.product.back_in_stock_email') }}</label>
            <input id="backInStockEmail" type="email" wire:model="backInStockEmail" autocomplete="email" required placeholder="{{ __('storefront.product.back_in_stock_email') }}">
            <button type="submit" class="store-btn store-btn--outline">{{ __('storefront.product.back_in_stock_action') }}</button>
        </form>
        @error('backInStockEmail') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        @if ($backInStockMessage !== '')
            <p class="store-field__success" role="status">{{ $backInStockMessage }}</p>
        @endif
    </section>
@endif

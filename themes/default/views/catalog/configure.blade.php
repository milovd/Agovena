@php
    $imagePath = $product->image_path ?: $product->images->first()?->path;
    $imageUrl = $imagePath ? \App\Agovena\Media\PublicMedia::url($imagePath) : null;
@endphp

<article class="store-product-configure">
    <nav class="store-breadcrumbs store-breadcrumbs--compact" aria-label="{{ __('storefront.breadcrumb_aria') }}">
        <a href="{{ route('storefront.product', $product->slug) }}">{{ $product->name }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ __('storefront.product.configure_breadcrumb') }}</span>
    </nav>

    <div class="store-product-configure__layout">
        <section class="store-product-configure__main" aria-labelledby="product-configuration-heading">
            <p class="store-product-configure__eyebrow">{{ __('storefront.product.configure_eyebrow') }}</p>
            <h1 id="product-configuration-heading" class="store-product-configure__title">{{ __('storefront.product.configure_title', ['product' => $product->name]) }}</h1>
            <p class="store-product-configure__lede">{{ __('storefront.product.configure_lede') }}</p>

            <form wire:submit="continueConfiguration" class="store-product__form">
                @include('theme::partials.product-options')

                <div class="store-product__buy">
                    <div
                        class="store-qty"
                        role="group"
                        aria-label="{{ __('storefront.product.quantity') }}"
                        x-data="storefrontQuantity"
                        data-min="1"
                        data-max="99"
                    >
                        <label class="visually-hidden" for="configuration-quantity">{{ __('storefront.product.quantity') }}</label>
                        <button type="button" class="store-qty__btn" @click="decrement()" aria-label="{{ __('storefront.product.decrease') }}">−</button>
                        <input id="configuration-quantity" class="store-qty__input" type="number" min="1" max="99" value="{{ $quantity }}" wire:model="quantity" x-ref="input" @change="normalize()">
                        <button type="button" class="store-qty__btn" @click="increment()" aria-label="{{ __('storefront.product.increase') }}">+</button>
                    </div>
                </div>

                <div class="store-product__actions store-product__actions--single">
                    <button type="submit" class="store-btn store-btn--primary store-btn--lg" wire:loading.attr="disabled" wire:target="continueConfiguration">
                        <span wire:loading.remove wire:target="continueConfiguration">{{ __('storefront.continue') }}</span>
                        <span wire:loading wire:target="continueConfiguration">{{ __('storefront.product.working') }}</span>
                    </button>
                </div>
                @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                @error('product') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </form>
        </section>

        <aside class="store-product-configure__summary" aria-labelledby="configuration-summary-heading">
            <div class="store-product-configure__media">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
                @else
                    <span class="store-product-card__placeholder store-product-card__placeholder--lg"></span>
                @endif
            </div>
            <div class="store-product-configure__summary-copy">
                <h2 id="configuration-summary-heading">{{ $product->name }}</h2>
                @if ($configuredPrice)
                    <strong>{{ \App\Support\MoneyFormatter::format($configuredPrice) }}</strong>
                @else
                    <span class="store-muted">{{ __('storefront.product.select_options_for_price') }}</span>
                @endif
            </div>
        </aside>
    </div>
</article>

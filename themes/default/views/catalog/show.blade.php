<article class="store-product">
    <nav class="store-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a>
        <span aria-hidden="true">/</span>
        @if ($product->category)
            @if ($product->category->parent)
                <a href="{{ route('storefront.category', $product->category->parent->slug) }}">{{ $product->category->parent->name }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <a href="{{ route('storefront.category', $product->category->slug) }}">{{ $product->category->name }}</a>
            <span aria-hidden="true">/</span>
        @endif
        <span aria-current="page">{{ $product->name }}</span>
    </nav>

    <div class="store-product__layout">
        <div class="store-product__gallery">
            <div class="store-product__media">
                @php
                    $gallery = $product->relationLoaded('images') ? $product->images : collect();
                    $primary = $gallery->isNotEmpty() ? $gallery->first()->path : $product->image_path;
                @endphp
                @if ($primary)
                    <img id="store-product-main-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($primary) }}" alt="{{ $product->name }}">
                @else
                    <span class="store-product-card__placeholder store-product-card__placeholder--lg"></span>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <ul class="store-product__thumbs" role="list">
                    @foreach ($gallery as $image)
                        <li>
                            <button
                                type="button"
                                class="store-product__thumb"
                                data-src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                                onclick="document.getElementById('store-product-main-image').src = this.dataset.src"
                            >
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="" loading="lazy">
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            {{-- Slot: variants / color swatches (future capability) --}}
        </div>

        <div class="store-product__info">
            @if ($product->category)
                <p class="store-product__eyebrow">
                    <a href="{{ route('storefront.category', $product->category->slug) }}">{{ $product->category->name }}</a>
                </p>
            @endif
            <h1 class="store-product__title">{{ $product->name }}</h1>
            {{-- Slot: ratings / reviews (future capability) --}}
            <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
            @if ($product->description)
                <div class="store-product__description">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <form wire:submit="addToCart" class="store-product__form">
                <div class="store-product__buy">
                    <div class="store-qty" role="group" aria-label="Quantity">
                        <label class="visually-hidden" for="quantity">Quantity</label>
                        <button type="button" class="store-qty__btn" wire:click="decrementQuantity" aria-label="Decrease quantity">−</button>
                        <input id="quantity" class="store-qty__input" type="number" min="1" max="99" wire:model="quantity">
                        <button type="button" class="store-qty__btn" wire:click="incrementQuantity" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="store-btn store-btn--primary store-btn--lg" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addToCart">Add to cart</span>
                        <span wire:loading wire:target="addToCart">Adding…</span>
                    </button>
                </div>
                @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </form>

            <ul class="store-product__trust" role="list">
                <li>
                    <span class="store-product__trust-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13l2 7H6"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/></svg>
                    </span>
                    <span>
                        <strong>Clear delivery</strong>
                        <span class="store-product__trust-text">Shipping options at checkout.</span>
                    </span>
                </li>
                <li>
                    <span class="store-product__trust-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/></svg>
                    </span>
                    <span>
                        <strong>Straightforward returns</strong>
                        <span class="store-product__trust-text">Policy details from the merchant.</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>

    @if (($related ?? collect())->isNotEmpty())
        <section class="store-section store-related" aria-labelledby="related-heading">
            <h2 id="related-heading" class="store-section__title">Related products</h2>
            @include('theme::partials.product-grid', [
                'products' => $related,
                'showExcerpt' => $themeConfig?->bool('catalog.show_excerpt', false) ?? false,
            ])
        </section>
    @endif
</article>

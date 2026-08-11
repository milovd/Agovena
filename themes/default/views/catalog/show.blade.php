<article class="store-product">
    <nav class="store-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a>
        <span aria-hidden="true">/</span>
        @if ($product->category)
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
        </div>
        <div class="store-product__info">
            @if ($product->category)
                <p class="store-product__eyebrow">
                    <a href="{{ route('storefront.category', $product->category->slug) }}">{{ $product->category->name }}</a>
                </p>
            @endif
            <h1 class="store-title">{{ $product->name }}</h1>
            <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
            @if ($product->description)
                <div class="store-product__description">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <form wire:submit="addToCart" class="store-product__form">
                <div class="store-field store-field--inline">
                    <label class="store-field__label" for="quantity">Quantity</label>
                    <input id="quantity" class="store-input store-input--narrow" type="number" min="1" max="99" wire:model="quantity">
                    @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="store-btn store-btn--primary store-btn--block" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="addToCart">Add to cart</span>
                    <span wire:loading wire:target="addToCart">Adding…</span>
                </button>
            </form>

            <ul class="store-product__trust" role="list">
                <li>Secure checkout</li>
                <li>Clear pricing</li>
                <li>Order confirmation by email</li>
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

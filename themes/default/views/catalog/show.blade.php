<article class="store-product">
    <div class="store-product__layout">
        <div class="store-product__media" aria-hidden="true">
            @if ($product->image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="">
            @else
                <span class="store-product-card__placeholder store-product-card__placeholder--lg"></span>
            @endif
        </div>
        <div class="store-product__info">
            <p class="store-product__eyebrow">
                <a href="{{ route('storefront.home') }}">Catalog</a>
            </p>
            <h1 class="store-title">{{ $product->name }}</h1>
            <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
            @if ($product->description)
                <div class="store-product__description">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <form wire:submit="addToCart" class="store-product__form">
                <div class="store-field">
                    <label class="store-field__label" for="quantity">Quantity</label>
                    <input id="quantity" class="store-input store-input--narrow" type="number" min="1" max="99" wire:model="quantity">
                    @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="store-btn store-btn--primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="addToCart">Add to cart</span>
                    <span wire:loading wire:target="addToCart">Adding…</span>
                </button>
            </form>
        </div>
    </div>
</article>

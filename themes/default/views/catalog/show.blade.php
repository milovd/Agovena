<article class="store-product">
    <h1 class="store-title">{{ $product->name }}</h1>
    @if ($product->description)
        <div class="store-product__description">{!! nl2br(e($product->description)) !!}</div>
    @endif
    <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>

    <form wire:submit="addToCart" class="store-product__form">
        <div class="store-field">
            <label class="store-field__label" for="quantity">Quantity</label>
            <input id="quantity" class="store-input" type="number" min="1" max="99" wire:model="quantity">
            @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="store-btn store-btn--primary">Add to cart</button>
    </form>
</article>

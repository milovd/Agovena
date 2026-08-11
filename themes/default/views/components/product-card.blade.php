<a class="store-product-card__link" href="{{ route('storefront.product', $product->slug) }}">
    <div class="store-product-card__media" aria-hidden="true">
        @if ($product->image_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="" loading="lazy">
        @else
            <span class="store-product-card__placeholder"></span>
        @endif
    </div>
    <div class="store-product-card__body">
        <h3 class="store-product-card__name">{{ $product->name }}</h3>
        @if (($showExcerpt ?? true) && $product->description)
            <p class="store-product-card__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 90) }}</p>
        @endif
        <p class="store-product-card__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
        <span class="store-product-card__cta">View product</span>
    </div>
</a>

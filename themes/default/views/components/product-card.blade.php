<a class="store-product-card__link" href="{{ route('storefront.product', $product->slug) }}">
    <div class="store-product-card__media">
        @php $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($product); @endphp
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="" loading="lazy">
        @else
            <span class="store-product-card__placeholder" aria-hidden="true"></span>
        @endif
    </div>
    <div class="store-product-card__body">
        @if ($product->category)
            <p class="store-product-card__meta">{{ $product->category->name }}</p>
        @endif
        <h3 class="store-product-card__name">{{ $product->name }}</h3>
        @if (($showExcerpt ?? false) && $product->description)
            <p class="store-product-card__excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 72) }}</p>
        @endif
        <div class="store-product-card__footer">
            <p class="store-product-card__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
            <span class="store-product-card__cta" aria-hidden="true" title="{{ __('storefront.product.view_product') }}">
                <svg class="store-product-card__cta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/>
                    <path d="m13 6 6 6-6 6"/>
                </svg>
            </span>
            <span class="visually-hidden">{{ __('storefront.product.view_named', ['name' => $product->name]) }}</span>
        </div>
    </div>
</a>

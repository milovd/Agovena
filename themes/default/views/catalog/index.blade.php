<div class="store-home">
    <section class="store-hero" aria-labelledby="hero-heading">
        <div class="store-hero__copy">
            <p class="store-hero__eyebrow">Welcome</p>
            <h1 id="hero-heading" class="store-hero__title">{{ $siteName ?? 'Your store' }}</h1>
            <p class="store-hero__lede">
                Discover products selected for quality and clarity — whether you shop for essentials,
                apparel, digital goods, or services.
            </p>
            <div class="store-hero__actions">
                <a class="store-btn store-btn--primary" href="#catalog">Browse catalog</a>
                <a class="store-btn" href="{{ route('storefront.cart') }}">View cart</a>
            </div>
        </div>
    </section>

    <section id="catalog" class="store-catalog" aria-labelledby="catalog-heading">
        <div class="store-section__header">
            <h2 id="catalog-heading" class="store-section__title">Catalog</h2>
            @if ($searchQuery !== '')
                <p class="store-section__lede">Results for “{{ $searchQuery }}”</p>
            @else
                <p class="store-section__lede">Browse everything currently available in the shop.</p>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="store-empty" role="status">
                <p class="store-empty__title">
                    @if ($searchQuery !== '')
                        No products match your search
                    @else
                        No products yet
                    @endif
                </p>
                <p class="store-empty__text">
                    @if ($searchQuery !== '')
                        Try a different term, or <a href="{{ route('storefront.home') }}">clear search</a>.
                    @else
                        Published products will appear here as a polished grid.
                    @endif
                </p>
            </div>
        @else
            <ul class="store-product-grid" role="list">
                @foreach ($products as $product)
                    <li class="store-product-card">
                        <a class="store-product-card__link" href="{{ route('storefront.product', $product->slug) }}">
                            <div class="store-product-card__media" aria-hidden="true">
                                @if ($product->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="">
                                @else
                                    <span class="store-product-card__placeholder"></span>
                                @endif
                            </div>
                            <div class="store-product-card__body">
                                <h3 class="store-product-card__name">{{ $product->name }}</h3>
                                <p class="store-product-card__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

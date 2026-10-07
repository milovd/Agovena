@php
    $gallery = $product->relationLoaded('images') ? $product->images : collect();
    $galleryPaths = $gallery->pluck('path')->filter()->values();
    if ($galleryPaths->isEmpty() && $product->image_path) {
        $galleryPaths = collect([$product->image_path]);
    }
    $galleryUrls = $galleryPaths
        ->map(fn (string $path) => \App\Agovena\Media\PublicMedia::url($path))
        ->filter()
        ->values()
        ->all();
    $reviewCount = 0;
    $ratingAverage = 0.0;
    $isDomainProduct = $product->hasCapability('domain_registration');
    $isPhysicalProduct = ! $isDomainProduct && $product->hasCapability('physical');
    $showDeliveryCard = $product->show_delivery_card ?? $isPhysicalProduct;
    $showReturnsCard = $product->show_returns_card ?? $isPhysicalProduct;
    $deliveryTitle = filled($product->delivery_title)
        ? $product->delivery_title
        : __('storefront.product.delivery_title');
    $deliveryText = filled($product->delivery_text)
        ? $product->delivery_text
        : __('storefront.product.delivery_text');
    $returnsTitle = filled($product->returns_title)
        ? $product->returns_title
        : __('storefront.product.returns_title');
    $returnsText = filled($product->returns_text)
        ? $product->returns_text
        : __('storefront.product.returns_text');
@endphp

<article class="store-product{{ $isDomainProduct ? ' store-product--domain' : '' }}">
    <nav class="store-breadcrumbs store-breadcrumbs--compact" aria-label="{{ __('storefront.breadcrumb_aria') }}">
        <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
        @if ($product->category)
            <span aria-hidden="true">/</span>
            @if ($product->category->parent)
                <a href="{{ route('storefront.category', $product->category->parent->slug) }}">{{ $product->category->parent->name }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <a href="{{ route('storefront.category', $product->category->slug) }}">{{ $product->category->name }}</a>
        @endif
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $product->name }}</span>
    </nav>

    <div class="store-product__layout">
        @include('theme::catalog.partials.product-gallery')

        <div class="store-product__info">
            <h1 class="store-product__title">{{ $product->name }}</h1>

            @if ($enableReviews ?? true)
                <div class="store-product__rating" aria-label="{{ __('storefront.product.rating_aria', ['rating' => number_format($ratingAverage, 1), 'count' => $reviewCount]) }}">
                    <span class="store-product__stars" aria-hidden="true">
                        @include('theme::catalog.partials.product-stars', ['rating' => $ratingAverage])
                    </span>
                    <span class="store-product__rating-score">{{ number_format($ratingAverage, 1) }}</span>
                    <a
                        class="store-product__rating-link"
                        href="#reviews"
                        @click.prevent="$dispatch('open-reviews')"
                    >{{ trans_choice('storefront.product.view_reviews', $reviewCount, ['count' => $reviewCount]) }}</a>
                </div>
            @endif

            @php
                $lede = filled($product->subtitle)
                    ? $product->subtitle
                    : ($product->description ? \Illuminate\Support\Str::limit(strip_tags($product->description), 140) : null);
            @endphp
            @if ($lede)
                <p class="store-product__lede">{{ $lede }}</p>
            @endif

            @include('theme::catalog.partials.product-purchase')

            @include('theme::catalog.partials.product-perks')
        </div>
    </div>

    @include('theme::catalog.partials.product-panels')

    @if (($related ?? collect())->isNotEmpty())
        <section class="store-section store-related" aria-labelledby="related-heading">
            <h2 id="related-heading" class="store-section__title">{{ __('storefront.product.related') }}</h2>
            @include('theme::partials.product-grid', [
                'products' => $related,
                'showExcerpt' => $themeConfig?->bool('catalog.show_excerpt', false) ?? false,
            ])
        </section>
    @endif
</article>

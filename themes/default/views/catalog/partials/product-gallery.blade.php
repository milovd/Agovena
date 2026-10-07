{{-- Product image gallery with thumbnail rail (Alpine: storefrontProductGallery). Expects $galleryUrls. --}}
<div
    class="store-product__gallery"
    @if (count($galleryUrls) > 0)
        x-data="storefrontProductGallery"
        data-images="{{ json_encode($galleryUrls) }}"
    @endif
>
    <div class="store-product__media">
        @if (count($galleryUrls) > 0)
            <img
                :src="currentImage()"
                src="{{ $galleryUrls[0] }}"
                alt="{{ $product->name }}"
                loading="eager"
                decoding="async"
                fetchpriority="high"
            >
        @else
            <span class="store-product-card__placeholder store-product-card__placeholder--lg"></span>
        @endif
    </div>

    @if (count($galleryUrls) > 1)
        <div class="store-product__thumbs-wrap">
            <button
                type="button"
                class="store-product__thumbs-arrow store-product__thumbs-arrow--prev"
                x-show="thumbsOverflow"
                x-cloak
                :class="arrowClass(canScrollLeft)"
                :disabled="arrowDisabled(canScrollLeft)"
                :aria-hidden="arrowAriaHidden(canScrollLeft)"
                @click="scrollThumbs(-1)"
                aria-label="{{ __('storefront.product.gallery_prev') }}"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
            </button>

            <ul class="store-product__thumbs" role="list" x-ref="track">
                @foreach ($galleryUrls as $i => $url)
                    <li>
                        <button
                            type="button"
                            class="store-product__thumb{{ $i === 0 ? ' is-active' : '' }}"
                            data-index="{{ $i }}"
                            :class="thumbClass({{ $i }})"
                            @click="select({{ $i }})"
                            :aria-current="thumbAriaCurrent({{ $i }})"
                            aria-label="{{ __('storefront.product.show_image', ['number' => $i + 1]) }}"
                        >
                            <img src="{{ $url }}" alt="" loading="lazy" decoding="async" fetchpriority="low">
                        </button>
                    </li>
                @endforeach
            </ul>

            <button
                type="button"
                class="store-product__thumbs-arrow store-product__thumbs-arrow--next"
                x-show="thumbsOverflow"
                x-cloak
                :class="arrowClass(canScrollRight)"
                :disabled="arrowDisabled(canScrollRight)"
                :aria-hidden="arrowAriaHidden(canScrollRight)"
                @click="scrollThumbs(1)"
                aria-label="{{ __('storefront.product.gallery_next') }}"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            </button>
        </div>
    @endif
</div>

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
@endphp

<article class="store-product">
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
        <div
            class="store-product__gallery"
            @if (count($galleryUrls) > 0)
                x-data="{
                    images: {{ \Illuminate\Support\Js::from($galleryUrls) }},
                    index: 0,
                    thumbsOverflow: false,
                    canScrollLeft: false,
                    canScrollRight: false,
                    select(i) {
                        this.index = i;
                        this.$nextTick(() => {
                            const thumb = this.$refs.track?.querySelector('[data-index=\'' + this.index + '\']');
                            thumb?.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
                            this.updateScrollState();
                        });
                    },
                    scrollThumbs(dir) {
                        const track = this.$refs.track;
                        if (! track) return;
                        const styles = getComputedStyle(track);
                        const gap = parseFloat(styles.columnGap || styles.gap) || 12;
                        const size = parseFloat(styles.getPropertyValue('--thumb-size')) || 72;
                        const step = Math.max(size + gap, track.clientWidth * 0.85);
                        track.scrollBy({ left: dir * step, behavior: 'smooth' });
                    },
                    layoutThumbs() {
                        const track = this.$refs.track;
                        if (! track) return;
                        const styles = getComputedStyle(track);
                        const gap = parseFloat(styles.columnGap || styles.gap) || 12;
                        const pad = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
                        const inner = Math.max(0, track.clientWidth - pad);
                        const count = track.children.length;
                        const minSize = 64;
                        const slots = Math.max(1, Math.floor((inner + gap) / (minSize + gap)));

                        if (count > slots && inner > 0) {
                            const size = (inner - ((slots - 1) * gap)) / slots;
                            track.style.setProperty('--thumb-size', size + 'px');
                            track.classList.add('is-fill');
                        } else {
                            track.style.removeProperty('--thumb-size');
                            track.classList.remove('is-fill');
                        }

                        this.updateScrollState();
                    },
                    updateScrollState() {
                        const track = this.$refs.track;
                        if (! track) {
                            this.thumbsOverflow = false;
                            this.canScrollLeft = false;
                            this.canScrollRight = false;
                            return;
                        }
                        const max = track.scrollWidth - track.clientWidth;
                        this.thumbsOverflow = max > 4;
                        this.canScrollLeft = this.thumbsOverflow && track.scrollLeft > 4;
                        this.canScrollRight = this.thumbsOverflow && track.scrollLeft < max - 4;
                    },
                    init() {
                        this.$nextTick(() => {
                            this.layoutThumbs();
                            const track = this.$refs.track;
                            if (! track) return;
                            track.addEventListener('scroll', () => this.updateScrollState(), { passive: true });
                            window.addEventListener('resize', () => this.layoutThumbs());
                            if (typeof ResizeObserver !== 'undefined') {
                                new ResizeObserver(() => this.layoutThumbs()).observe(track);
                            }
                        });
                    }
                }"
            @endif
        >
            <div class="store-product__media">
                @if (count($galleryUrls) > 0)
                    <img
                        :src="images[index]"
                        src="{{ $galleryUrls[0] }}"
                        alt="{{ $product->name }}"
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
                        :class="{ 'is-disabled': ! canScrollLeft }"
                        :disabled="! canScrollLeft"
                        :aria-hidden="(! canScrollLeft).toString()"
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
                                    :class="{ 'is-active': index === {{ $i }} }"
                                    @click="select({{ $i }})"
                                    :aria-current="index === {{ $i }} ? 'true' : 'false'"
                                    aria-label="{{ __('storefront.product.show_image', ['number' => $i + 1]) }}"
                                >
                                    <img src="{{ $url }}" alt="" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <button
                        type="button"
                        class="store-product__thumbs-arrow store-product__thumbs-arrow--next"
                        x-show="thumbsOverflow"
                        x-cloak
                        :class="{ 'is-disabled': ! canScrollRight }"
                        :disabled="! canScrollRight"
                        :aria-hidden="(! canScrollRight).toString()"
                        @click="scrollThumbs(1)"
                        aria-label="{{ __('storefront.product.gallery_next') }}"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                </div>
            @endif
        </div>

        <div class="store-product__info">
            <h1 class="store-product__title">{{ $product->name }}</h1>

            @if ($enableReviews ?? true)
                <div class="store-product__rating" aria-label="{{ __('storefront.product.rating_aria', ['rating' => number_format($ratingAverage, 1), 'count' => $reviewCount]) }}">
                    <span class="store-product__stars" aria-hidden="true">
                        @for ($i = 1; $i <= 5; $i++)
                            <svg class="store-product__star {{ $i <= (int) round($ratingAverage) ? 'is-filled' : '' }}" width="16" height="16" viewBox="0 0 24 24" focusable="false">
                                <path d="M12 3.5 14.7 9l6 .9-4.4 4.2 1 6L12 17.8 6.7 20.1l1-6L3.3 9.9l6-.9L12 3.5z"/>
                            </svg>
                        @endfor
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

            @if ($priceAvailable && $configuredPrice)
                <p class="store-product__price">{{ \App\Support\MoneyFormatter::format($configuredPrice) }}</p>
            @else
                <p class="store-product__price store-product__price--unavailable">{{ __('storefront.product.not_available_in_currency') }}</p>
            @endif

            @if ($priceAvailable)
            <form wire:submit="addToCart" class="store-product__form">
                @include('theme::partials.product-options')
                <div class="store-product__buy">
                    <div class="store-qty" role="group" aria-label="{{ __('storefront.product.quantity') }}">
                        <label class="visually-hidden" for="quantity">{{ __('storefront.product.quantity') }}</label>
                        <button type="button" class="store-qty__btn" wire:click="decrementQuantity" aria-label="{{ __('storefront.product.decrease') }}">−</button>
                        <input id="quantity" class="store-qty__input" type="number" min="1" max="99" wire:model="quantity">
                        <button type="button" class="store-qty__btn" wire:click="incrementQuantity" aria-label="{{ __('storefront.product.increase') }}">+</button>
                    </div>
                </div>

                <div class="store-product__actions">
                    <button type="button" class="store-btn store-btn--primary store-btn--lg" wire:click="buyNow" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="buyNow">{{ __('storefront.product.buy_now') }}</span>
                        <span wire:loading wire:target="buyNow">{{ __('storefront.product.working') }}</span>
                    </button>
                    <button type="submit" class="store-btn store-btn--outline store-btn--lg" wire:loading.attr="disabled" wire:target="addToCart">
                        <span wire:loading.remove wire:target="addToCart">{{ __('storefront.product.add_to_cart') }}</span>
                        <span wire:loading wire:target="addToCart">{{ __('storefront.product.adding') }}</span>
                    </button>
                </div>
                @error('quantity') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                @error('product') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </form>
            @endif

            <div class="store-product__perks" role="list">
                <div class="store-product__perk" role="listitem">
                    <span class="store-product__perk-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13l2 7H6"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/></svg>
                    </span>
                    <div>
                        <p class="store-product__perk-title">{{ __('storefront.product.delivery_title') }}</p>
                        <p class="store-product__perk-text">{{ __('storefront.product.delivery_text') }}</p>
                    </div>
                </div>
                <div class="store-product__perk" role="listitem">
                    <span class="store-product__perk-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/></svg>
                    </span>
                    <div>
                        <p class="store-product__perk-title">{{ __('storefront.product.returns_title') }}</p>
                        <p class="store-product__perk-text">{{ __('storefront.product.returns_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $showDetails = (bool) $product->show_details;
        $showSpecs = (bool) $product->show_specifications;
        $showDetailsPanel = $showDetails || $showSpecs;
        $reviewsOn = (bool) ($enableReviews ?? true);
        $specGroups = $showSpecs ? $product->specificationGroups() : [];
        $histogram = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $defaultTab = (! $showDetailsPanel && $reviewsOn) ? 'reviews' : 'details';
    @endphp

    @if ($showDetailsPanel || $reviewsOn)
    <section
        class="store-product-panels"
        id="product-tabs"
        x-data="{
            tab: (window.location.hash === '#reviews' && {{ $reviewsOn ? 'true' : 'false' }}) ? 'reviews' : '{{ $defaultTab }}',
            openReviews() {
                if (! {{ $reviewsOn ? 'true' : 'false' }}) return;
                this.tab = 'reviews';
                history.replaceState(null, '', '#reviews');
                this.$nextTick(() => this.$refs.reviews?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        }"
        @open-reviews.window="openReviews()"
        @open-reviews="openReviews()"
    >
        @if ($showDetailsPanel && $reviewsOn)
            <div class="store-product-panels__tabs" role="tablist" aria-label="{{ __('storefront.product.tabs_aria') }}">
                <button
                    type="button"
                    class="store-product-panels__tab"
                    role="tab"
                    id="tab-details"
                    :aria-selected="(tab === 'details').toString()"
                    :class="{ 'is-active': tab === 'details' }"
                    @click="tab = 'details'; history.replaceState(null, '', '#details')"
                >{{ __('storefront.product.tab_details') }}</button>
                <button
                    type="button"
                    class="store-product-panels__tab"
                    role="tab"
                    id="tab-reviews"
                    :aria-selected="(tab === 'reviews').toString()"
                    :class="{ 'is-active': tab === 'reviews' }"
                    @click="openReviews()"
                >{{ __('storefront.product.tab_reviews') }}</button>
            </div>
        @elseif ($showDetailsPanel)
            <h2 class="store-product-details__heading">{{ __('storefront.product.tab_details') }}</h2>
        @elseif ($reviewsOn)
            <h2 class="store-product-details__heading">{{ __('storefront.product.tab_reviews') }}</h2>
        @endif

        @if ($showDetailsPanel)
        <div
            class="store-product-panels__panel"
            role="tabpanel"
            aria-labelledby="tab-details"
            @if ($reviewsOn) x-show="tab === 'details'" @endif
        >
            <div class="store-product-details">
                @if ($showDetails && $product->description)
                    <div class="store-product-details__copy">
                        <h3 class="store-product-details__heading">{{ __('storefront.product.about') }}</h3>
                        <div class="store-product-details__body">{!! nl2br(e($product->description)) !!}</div>
                    </div>
                @endif

                @if ($showSpecs)
                    @foreach ($specGroups as $group)
                        <div class="store-product-specs">
                            <h3 class="store-product-specs__title">{{ $group['title'] }}</h3>
                            <dl class="store-product-specs__table">
                                @foreach ($group['rows'] as $row)
                                    <div class="store-product-specs__row">
                                        <dt>{{ $row['label'] }}</dt>
                                        <dd>{{ $row['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
        @endif

        @if ($reviewsOn)
        <div
            class="store-product-panels__panel"
            role="tabpanel"
            aria-labelledby="tab-reviews"
            id="reviews"
            x-ref="reviews"
            @if ($showDetailsPanel) x-show="tab === 'reviews'" x-cloak @endif
        >
            <div class="store-product-reviews">
                <div class="store-product-reviews__main">
                    <div class="store-product-reviews__toolbar">
                        <p class="store-product-reviews__sort" aria-hidden="true">{{ __('storefront.product.reviews_sort_newest') }}</p>
                    </div>

                    @if ($reviewCount === 0)
                        <div class="store-product-reviews__empty" role="status">
                            <p class="store-product-reviews__empty-title">{{ __('storefront.product.reviews_empty_title') }}</p>
                            <p class="store-product-reviews__empty-text">{{ __('storefront.product.reviews_empty_text') }}</p>
                        </div>
                    @endif
                </div>

                <aside class="store-product-reviews__summary" aria-label="{{ __('storefront.product.rating_summary_aria') }}">
                    <div class="store-product-reviews__score">
                        <p class="store-product-reviews__score-value">{{ number_format($ratingAverage, 1) }}</p>
                        <div>
                            <span class="store-product__stars" aria-hidden="true">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg class="store-product__star {{ $i <= (int) round($ratingAverage) ? 'is-filled' : '' }}" width="16" height="16" viewBox="0 0 24 24" focusable="false">
                                        <path d="M12 3.5 14.7 9l6 .9-4.4 4.2 1 6L12 17.8 6.7 20.1l1-6L3.3 9.9l6-.9L12 3.5z"/>
                                    </svg>
                                @endfor
                            </span>
                            <p class="store-product-reviews__score-meta">{{ trans_choice('storefront.product.reviews_count', $reviewCount, ['count' => $reviewCount]) }}</p>
                        </div>
                    </div>

                    <ul class="store-product-reviews__bars" role="list">
                        @foreach ($histogram as $stars => $count)
                            <li class="store-product-reviews__bar-row">
                                <span class="store-product-reviews__bar-label">{{ $stars }}</span>
                                <span class="store-product-reviews__bar-track" aria-hidden="true">
                                    <span class="store-product-reviews__bar-fill" style="width: 0%"></span>
                                </span>
                                <span class="store-product-reviews__bar-count">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>
        </div>
        @endif
    </section>
    @endif

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

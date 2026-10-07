{{-- Product details/specifications and reviews panels (Alpine: storefrontProductPanels). --}}
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
    x-data="storefrontProductPanels"
    data-reviews-on="{{ $reviewsOn ? 'true' : 'false' }}"
    data-default-tab="{{ $defaultTab }}"
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
                @click="selectTab('details')"
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
                                @include('theme::catalog.partials.product-stars', ['rating' => $ratingAverage])
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

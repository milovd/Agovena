@php
    $showExcerpt = ($themeConfig?->bool('catalog.show_excerpt', false) ?? false);
@endphp

<div class="store-home">
    @if ($isSearch)
        <section id="catalog" class="store-catalog" aria-labelledby="catalog-heading">
            <div class="store-section__header">
                <h1 id="catalog-heading" class="store-section__title">{{ __('storefront.search.heading') }}</h1>
                <p class="store-section__lede">{{ __('storefront.search.results_for', ['query' => $searchQuery]) }}</p>
            </div>

            @if ($products->isEmpty())
                <div class="store-empty" role="status">
                    <p class="store-empty__title">{{ __('storefront.search.no_products') }}</p>
                    <p class="store-empty__text">{!! __('storefront.search.try_different', ['url' => route('storefront.home')]) !!}</p>
                </div>
            @else
                @include('theme::partials.product-grid', ['products' => $products, 'showExcerpt' => $showExcerpt])
            @endif
        </section>
    @else
        @foreach ($sections as $section)
            @php $type = $section['type'] ?? ''; @endphp
            @if ($type === 'hero')
                @include('theme::sections.hero', [
                    'section' => $section,
                    'siteName' => $siteName,
                    'spotlightProducts' => $products,
                ])
            @elseif ($type === 'featured_categories')
                @include('theme::sections.featured-categories', ['section' => $section, 'categories' => $categories])
            @elseif ($type === 'featured_products')
                @php
                    $limit = (int) ($section['limit'] ?? 8);
                    $featured = $products->take($limit > 0 ? $limit : 8);
                @endphp
                @include('theme::sections.featured-products', [
                    'section' => $section,
                    'products' => $featured,
                    'showExcerpt' => $showExcerpt,
                ])
            @elseif ($type === 'promo_split')
                @include('theme::sections.promo-split', ['section' => $section])
            @elseif ($type === 'trust_strip')
                @include('theme::sections.trust-strip', ['section' => $section])
            @elseif ($type === 'rich_text')
                @include('theme::sections.rich-text', ['section' => $section])
            @endif
        @endforeach

        @if ($products->isEmpty() && collect($sections)->where('type', 'featured_products')->isEmpty())
            <section id="catalog" class="store-catalog" aria-labelledby="catalog-heading">
                <div class="store-empty" role="status">
                    <p class="store-empty__title">{{ __('storefront.catalog.empty_title') }}</p>
                    <p class="store-empty__text">{!! __('storefront.catalog.empty_text') !!}</p>
                </div>
            </section>
        @endif
    @endif
</div>

<section class="store-catalog" aria-labelledby="category-heading">
    <nav class="store-breadcrumbs" aria-label="{{ __('storefront.breadcrumb_aria') }}">
        <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $category->name }}</span>
    </nav>

    <div class="store-section__header">
        <h1 id="category-heading" class="store-section__title">{{ $category->name }}</h1>
        @if ($category->description)
            <p class="store-section__lede">{{ $category->description }}</p>
        @endif
    </div>

    <form class="store-toolbar" method="get" action="{{ route('storefront.category', $category->slug) }}">
        <div class="store-toolbar__search">
            <label class="visually-hidden" for="category-search">{{ __('storefront.catalog.filter_label') }}</label>
            <input id="category-search" class="store-input" type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ __('storefront.catalog.filter_placeholder') }}">
        </div>
        <div class="store-toolbar__sort">
            <label class="visually-hidden" for="category-sort">{{ __('storefront.catalog.sort') }}</label>
            <select id="category-sort" class="store-input" name="sort" onchange="this.form.submit()">
                <option value="name" @selected($sort === 'name')>{{ __('storefront.catalog.sort_name') }}</option>
                <option value="price_asc" @selected($sort === 'price_asc')>{{ __('storefront.catalog.sort_price_asc') }}</option>
                <option value="price_desc" @selected($sort === 'price_desc')>{{ __('storefront.catalog.sort_price_desc') }}</option>
            </select>
        </div>
        <button type="submit" class="store-btn">{{ __('storefront.catalog.apply') }}</button>
    </form>

    @if ($products->isEmpty())
        <div class="store-empty" role="status">
            <p class="store-empty__title">{{ __('storefront.catalog.empty_category_title') }}</p>
            <p class="store-empty__text"><a href="{{ route('storefront.home') }}">{{ __('storefront.catalog.back_to_home') }}</a></p>
        </div>
    @else
        @include('theme::partials.product-grid', [
            'products' => $products,
            'showExcerpt' => $themeConfig?->bool('catalog.show_excerpt', true) ?? true,
        ])
    @endif
</section>

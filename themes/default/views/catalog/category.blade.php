<section class="store-catalog" aria-labelledby="category-heading">
    <nav class="store-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a>
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
            <label class="visually-hidden" for="category-search">Filter in category</label>
            <input id="category-search" class="store-input" type="search" name="q" value="{{ $searchQuery }}" placeholder="Filter products">
        </div>
        <div class="store-toolbar__sort">
            <label class="visually-hidden" for="category-sort">Sort</label>
            <select id="category-sort" class="store-input" name="sort" onchange="this.form.submit()">
                <option value="name" @selected($sort === 'name')>Name</option>
                <option value="price_asc" @selected($sort === 'price_asc')>Price: low to high</option>
                <option value="price_desc" @selected($sort === 'price_desc')>Price: high to low</option>
            </select>
        </div>
        <button type="submit" class="store-btn">Apply</button>
    </form>

    @if ($products->isEmpty())
        <div class="store-empty" role="status">
            <p class="store-empty__title">No products in this category</p>
            <p class="store-empty__text"><a href="{{ route('storefront.home') }}">Back to home</a></p>
        </div>
    @else
        @include('theme::partials.product-grid', [
            'products' => $products,
            'showExcerpt' => $themeConfig?->bool('catalog.show_excerpt', true) ?? true,
        ])
    @endif
</section>

@if ($categories->isNotEmpty())
<section class="store-section store-categories" aria-labelledby="categories-heading">
    <div class="store-section__header">
        <h2 id="categories-heading" class="store-section__title">{{ $section['title'] ?? 'Shop by category' }}</h2>
        @if (! empty($section['lede']))
            <p class="store-section__lede">{{ $section['lede'] }}</p>
        @endif
    </div>
    <ul class="store-category-grid" role="list">
        @foreach ($categories as $category)
            <li>
                <a class="store-category-card" href="{{ route('storefront.category', $category->slug) }}">
                    <span class="store-category-card__name">{{ $category->name }}</span>
                    <span class="store-category-card__meta">{{ $category->products_count }} {{ $category->products_count === 1 ? 'item' : 'items' }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
@endif

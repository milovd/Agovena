@if ($categories->isNotEmpty())
<section id="categories" class="store-section store-categories" aria-labelledby="categories-heading">
    <div class="store-section__header store-section__header--row">
        <div>
            <h2 id="categories-heading" class="store-section__title">{{ $section['title'] ?? 'Shop by category' }}</h2>
            @if (! empty($section['lede']))
                <p class="store-section__lede">{{ $section['lede'] }}</p>
            @endif
        </div>
    </div>
    <ul class="store-category-grid" role="list">
        @foreach ($categories as $category)
            <li>
                <a class="store-category-card" href="{{ route('storefront.category', $category->slug) }}">
                    <span class="store-category-card__media" aria-hidden="true">
                        @if ($category->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" loading="lazy">
                        @else
                            <span class="store-category-card__placeholder">{{ mb_substr($category->name, 0, 1) }}</span>
                        @endif
                    </span>
                    <span class="store-category-card__body">
                        <span class="store-category-card__name">{{ $category->name }}</span>
                        <span class="store-category-card__meta">{{ $category->products_count }} {{ $category->products_count === 1 ? 'item' : 'items' }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
@endif

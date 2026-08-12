@if ($categories->isNotEmpty())
@php
    $tileTone = ['tone-a', 'tone-b', 'tone-c', 'tone-d'];
@endphp
<section id="categories" class="store-section store-categories" aria-labelledby="categories-heading">
    <div class="store-section__header store-section__header--row">
        <div>
            <h2 id="categories-heading" class="store-section__title">{{ $section['title'] ?? 'Jump in' }}</h2>
            @if (! empty($section['lede']))
                <p class="store-section__lede">{{ $section['lede'] }}</p>
            @endif
        </div>
        <a class="store-section__link" href="{{ route('storefront.categories') }}">{{ __('storefront.nav.all_categories') }}</a>
    </div>
    <ul class="store-promo-tiles" role="list">
        @foreach ($categories->take(4) as $index => $category)
            <li>
                <a class="store-promo-tile store-promo-tile--{{ $tileTone[$index % 4] }}" href="{{ route('storefront.category', $category->slug) }}">
                    <span class="store-promo-tile__copy">
                        <span class="store-promo-tile__name">{{ $category->name }}</span>
                        <span class="store-promo-tile__meta">{{ trans_choice('storefront.categories.items', (int) $category->products_count, ['count' => (int) $category->products_count]) }}</span>
                    </span>
                    <span class="store-promo-tile__media" aria-hidden="true">
                        @if ($category->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" loading="lazy">
                        @else
                            <span class="store-promo-tile__fallback">{{ mb_substr($category->name, 0, 1) }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
@endif

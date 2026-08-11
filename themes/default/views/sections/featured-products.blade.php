<section id="catalog" class="store-section store-catalog" aria-labelledby="featured-heading">
    <div class="store-section__header">
        <h2 id="featured-heading" class="store-section__title">{{ $section['title'] ?? 'Featured products' }}</h2>
        @if (! empty($section['lede']))
            <p class="store-section__lede">{{ $section['lede'] }}</p>
        @endif
    </div>

    @if ($products->isEmpty())
        <div class="store-empty" role="status">
            <p class="store-empty__title">No products yet</p>
            <p class="store-empty__text">Published products will appear in this section.</p>
        </div>
    @else
        @include('theme::partials.product-grid', ['products' => $products, 'showExcerpt' => $showExcerpt])
    @endif
</section>

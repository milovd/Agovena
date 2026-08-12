<article class="store-catalog">
    <header class="store-catalog__header">
        <h1 class="store-title">{{ __('storefront.categories.title') }}</h1>
        <p class="store-catalog__lede">{{ __('storefront.categories.lede') }}</p>
    </header>

    @if ($categories->isEmpty())
        <div class="store-empty" role="status">
            <p class="store-empty__title">{{ __('storefront.categories.empty_title') }}</p>
            <p class="store-empty__text">{{ __('storefront.categories.empty_text') }}</p>
        </div>
    @else
        <ul class="store-category-grid store-category-grid--page" role="list">
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
                            <span class="store-category-card__meta">
                                @php
                                    $itemCount = (int) $category->products_count + (int) $category->children->sum('products_count');
                                @endphp
                                {{ trans_choice('storefront.categories.items', $itemCount, ['count' => $itemCount]) }}
                            </span>
                        </span>
                    </a>
                    @if ($category->children->isNotEmpty())
                        <ul class="store-category-card__children" role="list">
                            @foreach ($category->children as $child)
                                <li>
                                    <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</article>

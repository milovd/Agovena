{{-- Desktop category mega menu inside the primary nav (Alpine scope: storefrontHeader). --}}
@if ($categoriesOn && $discoveryCategories->isNotEmpty())
    <div
        class="store-cats"
        @mouseenter="openCategories()"
        @mouseleave="closeCategories()"
        @focusin="openCategories()"
        @click.outside="closeCategories()"
    >
        <a
            href="{{ route('storefront.categories') }}"
            class="store-nav__link store-nav__link--btn"
            :aria-expanded="catsOpen.toString()"
            aria-controls="store-cats-panel"
            aria-haspopup="true"
        >
            {{ __('storefront.nav.categories') }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </a>
        <div
            id="store-cats-panel"
            class="store-cats__panel"
            x-show="catsOpen"
            x-cloak
            x-transition.opacity.duration.120ms
            role="region"
            aria-label="{{ __('storefront.nav.categories') }}"
        >
            <div class="store-cats__panel-inner">
            <ul class="store-cats__roots" role="list">
                @foreach ($discoveryCategories as $category)
                    <li
                        @mouseenter="setActiveCategory({{ $category->id }})"
                        :class="categoryClass({{ $category->id }}, {{ $loop->first ? 'true' : 'false' }})"
                    >
                        <a class="store-cats__root" href="{{ route('storefront.category', $category->slug) }}">
                            <span class="store-cats__thumb" aria-hidden="true">
                                @php $categoryImageUrl = \App\Agovena\Media\PublicMedia::url($category->image_path); @endphp
                                @if ($categoryImageUrl)
                                    <img src="{{ $categoryImageUrl }}" alt="">
                                @endif
                            </span>
                            <span class="store-cats__label">{{ $category->name }}</span>
                            @if ($category->children->isNotEmpty())
                                <svg class="store-cats__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="store-cats__subs">
                @foreach ($discoveryCategories as $category)
                    <div
                        class="store-cats__subpane"
                        x-show="isCategoryActive({{ $category->id }}, {{ $loop->first ? 'true' : 'false' }})"
                        x-cloak
                    >
                        <p class="store-cats__subhead">{{ $category->name }}</p>
                        <a class="store-cats__all" href="{{ route('storefront.category', $category->slug) }}">{{ __('storefront.nav.shop_all', ['name' => $category->name]) }}</a>
                        @if ($category->children->isNotEmpty())
                            <ul class="store-cats__children" role="list">
                                @foreach ($category->children as $child)
                                    <li>
                                        <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="store-cats__empty">{{ __('storefront.nav.browse_collection') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            </div>
        </div>
    </div>
@endif

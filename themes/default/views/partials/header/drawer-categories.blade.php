{{-- Category tree inside the mobile drawer (Alpine scope: storefrontHeader). --}}
@if ($categoriesOn && $discoveryCategories->isNotEmpty())
    <div class="store-drawer__categories" :class="{ 'is-open': mobileCatsOpen }">
        <button
            type="button"
            class="store-nav__link store-nav__link--btn store-drawer__primary-link"
            @click="toggleMobileCategories()"
            :aria-expanded="mobileCatsOpen.toString()"
            aria-controls="store-mobile-categories"
        >
            <span>{{ __('storefront.nav.categories') }}</span>
            <svg class="store-drawer__category-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div
            id="store-mobile-categories"
            class="store-drawer__category-panel"
            x-show="mobileCatsOpen"
            x-cloak
            x-transition:enter="store-drawer__category-panel--enter"
            x-transition:enter-start="store-drawer__category-panel--enter-start"
            x-transition:enter-end="store-drawer__category-panel--enter-end"
            x-transition:leave="store-drawer__category-panel--leave"
            x-transition:leave-start="store-drawer__category-panel--leave-start"
            x-transition:leave-end="store-drawer__category-panel--leave-end"
            role="region"
            aria-label="{{ __('storefront.nav.categories') }}"
        >
            <a class="store-drawer__category-all" href="{{ route('storefront.categories') }}" @click="closeDrawerOnNavigate">{{ __('storefront.nav.all_categories') }}</a>
            @foreach ($discoveryCategories as $category)
                <div class="store-drawer__category-group">
                    <div class="store-drawer__category-row" :class="{ 'is-open': mobileCategoryOpen === {{ $category->id }} }">
                        <a class="store-drawer__category-root" href="{{ route('storefront.category', $category->slug) }}" @click="closeDrawerOnNavigate">
                            <span class="store-cats__thumb" aria-hidden="true">
                                @php $categoryImageUrl = \App\Agovena\Media\PublicMedia::url($category->image_path); @endphp
                                @if ($categoryImageUrl)
                                    <img src="{{ $categoryImageUrl }}" alt="">
                                @endif
                            </span>
                            <span class="store-cats__label">{{ $category->name }}</span>
                        </a>
                        @if ($category->children->isNotEmpty())
                            <button
                                type="button"
                                class="store-drawer__category-toggle"
                                @click="toggleMobileCategory({{ $category->id }})"
                                :aria-expanded="(mobileCategoryOpen === {{ $category->id }}).toString()"
                                aria-controls="store-mobile-category-{{ $category->id }}"
                                aria-label="{{ __('storefront.nav.categories') }}: {{ $category->name }}"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                        @endif
                    </div>
                    @if ($category->children->isNotEmpty())
                        <div
                            id="store-mobile-category-{{ $category->id }}"
                            class="store-drawer__category-children"
                            x-show="mobileCategoryOpen === {{ $category->id }}"
                            x-bind:hidden="mobileCategoryOpen !== {{ $category->id }}"
                            x-cloak
                            x-transition:enter="store-drawer__category-children--enter"
                            x-transition:enter-start="store-drawer__category-children--enter-start"
                            x-transition:enter-end="store-drawer__category-children--enter-end"
                            x-transition:leave="store-drawer__category-children--leave"
                            x-transition:leave-start="store-drawer__category-children--leave-start"
                            x-transition:leave-end="store-drawer__category-children--leave-end"
                            role="region"
                            aria-label="{{ $category->name }}"
                        >
                            @foreach ($category->children as $child)
                                <a href="{{ route('storefront.category', $child->slug) }}" @click="closeDrawerOnNavigate">{{ $child->name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

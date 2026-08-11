@php
    $cfg = $themeConfig ?? app(\App\Agovena\Theme\ThemeManager::class)->config();
    $announcementOn = $cfg->bool('header.announcement_enabled', true);
    $announcementText = $cfg->string('header.announcement_text');
    $announcementLink = $cfg->string('header.announcement_link');
    $searchOn = $cfg->bool('header.search_enabled', true);
    $showAccount = $cfg->bool('header.show_account', true);
    $categoriesOn = $cfg->bool('header.show_discovery_bar', true);
    $discoveryCategories = $discoveryCategories ?? collect();
    $suggestUrl = route('storefront.search.suggest');
@endphp

@if ($announcementOn && $announcementText !== '')
    <div class="store-announce" role="region" aria-label="Announcement">
        <div class="store-announce__inner">
            @if ($announcementLink !== '')
                <a class="store-announce__link" href="{{ $announcementLink }}">{{ $announcementText }}</a>
            @else
                <p class="store-announce__text">{{ $announcementText }}</p>
            @endif
        </div>
    </div>
@endif

<header
    class="store-chrome"
    x-data="{
        navOpen: false,
        catsOpen: false,
        activeCat: null,
        suggestOpen: false,
        suggestLoading: false,
        suggestItems: [],
        suggestQuery: @js(request('q', '')),
        suggestUrl: @js($suggestUrl),
        suggestTimer: null,
        async runSuggest() {
            const q = (this.suggestQuery || '').trim();
            if (q.length < 2) {
                this.suggestItems = [];
                this.suggestOpen = false;
                return;
            }
            this.suggestLoading = true;
            try {
                const res = await fetch(this.suggestUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.suggestItems = data.items || [];
                this.suggestOpen = true;
            } catch (e) {
                this.suggestItems = [];
                this.suggestOpen = false;
            } finally {
                this.suggestLoading = false;
            }
        },
        onSuggestInput() {
            clearTimeout(this.suggestTimer);
            this.suggestTimer = setTimeout(() => this.runSuggest(), 180);
        },
        closeSuggest() {
            this.suggestOpen = false;
        }
    }"
    @keydown.escape.window="navOpen = false; catsOpen = false; suggestOpen = false"
>
    <div class="store-header">
        <div class="store-header__inner">
            <button
                type="button"
                class="store-header__menu"
                @click="navOpen = !navOpen"
                :aria-expanded="navOpen.toString()"
                aria-controls="store-mobile-nav"
            >
                <span class="store-header__menu-bars" aria-hidden="true"></span>
                <span class="visually-hidden">Menu</span>
            </button>

            <a class="store-brand" href="{{ route('storefront.home') }}">
                @if (! empty($brandingLogoPath))
                    <img
                        class="store-brand__logo"
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingLogoPath) }}"
                        alt="{{ $siteName ?? 'Store' }}"
                    >
                @else
                    <span class="store-brand__name">{{ $siteName ?? 'Store' }}</span>
                @endif
            </a>

            <nav class="store-nav" aria-label="Primary">
                @if ($categoriesOn && $discoveryCategories->isNotEmpty())
                    <div class="store-cats" @mouseleave="catsOpen = false; activeCat = null">
                        <button
                            type="button"
                            class="store-nav__link store-nav__link--btn"
                            @click="catsOpen = !catsOpen"
                            @mouseenter="catsOpen = true"
                            :aria-expanded="catsOpen.toString()"
                            aria-controls="store-cats-panel"
                        >
                            Categories
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div
                            id="store-cats-panel"
                            class="store-cats__panel"
                            x-show="catsOpen"
                            x-cloak
                            @click.outside="catsOpen = false"
                            role="region"
                            aria-label="Categories"
                        >
                            <ul class="store-cats__roots" role="list">
                                @foreach ($discoveryCategories as $category)
                                    <li
                                        @mouseenter="activeCat = {{ $category->id }}"
                                        :class="{ 'is-active': activeCat === {{ $category->id }} || (activeCat === null && {{ $loop->first ? 'true' : 'false' }}) }"
                                    >
                                        <a class="store-cats__root" href="{{ route('storefront.category', $category->slug) }}">
                                            <span class="store-cats__thumb" aria-hidden="true">
                                                @if ($category->image_path)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="">
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
                                        x-show="activeCat === {{ $category->id }} || (activeCat === null && {{ $loop->first ? 'true' : 'false' }})"
                                        x-cloak
                                    >
                                        <p class="store-cats__subhead">{{ $category->name }}</p>
                                        <a class="store-cats__all" href="{{ route('storefront.category', $category->slug) }}">Shop all {{ $category->name }}</a>
                                        @if ($category->children->isNotEmpty())
                                            <ul class="store-cats__children" role="list">
                                                @foreach ($category->children as $child)
                                                    <li>
                                                        <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="store-cats__empty">Browse this collection for featured products.</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']))
                        <a class="store-nav__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            @if ($searchOn)
                <div class="store-header__search-wrap" @click.outside="closeSuggest()">
                    <form class="store-header__search" action="{{ route('storefront.home') }}" method="get" role="search">
                        <label class="visually-hidden" for="store-header-search">Search products</label>
                        <svg class="store-header__search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                        <input
                            id="store-header-search"
                            class="store-header__search-input"
                            type="search"
                            name="q"
                            x-model="suggestQuery"
                            @input="onSuggestInput()"
                            @focus="onSuggestInput()"
                            placeholder="Search products"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-controls="store-search-suggest"
                        >
                    </form>
                    <div
                        id="store-search-suggest"
                        class="store-suggest"
                        x-show="suggestOpen"
                        x-cloak
                        role="listbox"
                        aria-label="Search suggestions"
                    >
                        <template x-if="suggestLoading">
                            <p class="store-suggest__status">Searching…</p>
                        </template>
                        <template x-if="!suggestLoading && suggestItems.length === 0 && (suggestQuery || '').trim().length >= 2">
                            <p class="store-suggest__status">No matches</p>
                        </template>
                        <template x-for="item in suggestItems" :key="item.slug">
                            <a class="store-suggest__item" :href="item.url" role="option">
                                <span class="store-suggest__media" aria-hidden="true">
                                    <img x-show="item.image" :src="item.image" alt="">
                                </span>
                                <span class="store-suggest__copy">
                                    <span class="store-suggest__name" x-text="item.name"></span>
                                    <span class="store-suggest__meta" x-text="item.category || ''"></span>
                                </span>
                                <span class="store-suggest__price" x-text="item.price"></span>
                            </a>
                        </template>
                        <a
                            class="store-suggest__all"
                            :href="'{{ route('storefront.home') }}?q=' + encodeURIComponent((suggestQuery || '').trim())"
                            x-show="(suggestQuery || '').trim().length >= 2"
                        >View all results</a>
                    </div>
                </div>
            @endif

            <div class="store-header__actions">
                @if ($showAccount)
                    <span
                        class="store-header__utility"
                        title="Customer accounts will be available when the customer portal ships"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        <span class="store-header__utility-label">Account</span>
                    </span>
                @endif
                <a class="store-header__utility store-header__cart" href="{{ route('storefront.cart') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    <span class="store-header__utility-label">Cart</span>
                    @if (($cartCount ?? 0) > 0)
                        <span class="store-header__cart-count" aria-label="{{ $cartCount }} items in cart">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        @if ($searchOn)
            <div class="store-header__search-wrap store-header__search-wrap--mobile" @click.outside="closeSuggest()">
                <form class="store-header__search store-header__search--mobile" action="{{ route('storefront.home') }}" method="get" role="search">
                    <label class="visually-hidden" for="store-header-search-mobile">Search products</label>
                    <input
                        id="store-header-search-mobile"
                        class="store-header__search-input"
                        type="search"
                        name="q"
                        x-model="suggestQuery"
                        @input="onSuggestInput()"
                        @focus="onSuggestInput()"
                        placeholder="Search products"
                        autocomplete="off"
                    >
                    <button type="submit" class="store-btn store-btn--primary">Search</button>
                </form>
            </div>
        @endif
    </div>

    <div
        id="store-mobile-nav"
        class="store-drawer"
        x-show="navOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
    >
        <div class="store-drawer__backdrop" @click="navOpen = false"></div>
        <div class="store-drawer__panel">
            <p class="store-drawer__title">Menu</p>
            <nav class="store-drawer__nav" aria-label="Mobile">
                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']))
                        <a class="store-drawer__link" href="{{ $item['url'] }}" @click="navOpen = false">{{ $item['label'] }}</a>
                    @endif
                @endforeach
                @if ($categoriesOn && $discoveryCategories->isNotEmpty())
                    <p class="store-drawer__label">Categories</p>
                    @foreach ($discoveryCategories as $category)
                        <a class="store-drawer__link" href="{{ route('storefront.category', $category->slug) }}" @click="navOpen = false">{{ $category->name }}</a>
                        @foreach ($category->children as $child)
                            <a class="store-drawer__link store-drawer__link--child" href="{{ route('storefront.category', $child->slug) }}" @click="navOpen = false">{{ $child->name }}</a>
                        @endforeach
                    @endforeach
                @endif
                <a class="store-drawer__link" href="{{ route('storefront.cart') }}" @click="navOpen = false">
                    Cart
                    @if (($cartCount ?? 0) > 0)
                        ({{ $cartCount }})
                    @endif
                </a>
            </nav>
            <button type="button" class="store-btn" @click="navOpen = false">Close</button>
        </div>
    </div>
</header>

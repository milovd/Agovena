@php
    $cfg = $themeConfig ?? app(\App\Agovena\Theme\ThemeManager::class)->config();
    $announcementOn = $cfg->bool('header.announcement_enabled', true);
    $announcementText = $cfg->string('header.announcement_text');
    $announcementLink = $cfg->string('header.announcement_link');
    $searchOn = $cfg->bool('header.search_enabled', true);
    $showAccount = $cfg->bool('header.show_account', true);
    $discoveryOn = $cfg->bool('header.show_discovery_bar', true);
    $discoveryCategories = $discoveryCategories ?? collect();
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

<header class="store-chrome">
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

            @if ($searchOn)
                <form class="store-header__search" action="{{ route('storefront.home') }}" method="get" role="search">
                    <label class="visually-hidden" for="store-header-search">Search products</label>
                    <svg class="store-header__search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                    <input
                        id="store-header-search"
                        class="store-header__search-input"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search products"
                        autocomplete="off"
                    >
                    <button type="submit" class="store-btn store-btn--primary store-header__search-submit">Search</button>
                </form>
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
            <form class="store-header__search store-header__search--mobile" action="{{ route('storefront.home') }}" method="get" role="search">
                <label class="visually-hidden" for="store-header-search-mobile">Search products</label>
                <input
                    id="store-header-search-mobile"
                    class="store-header__search-input"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search products"
                    autocomplete="off"
                >
                <button type="submit" class="store-btn store-btn--primary">Search</button>
            </form>
        @endif
    </div>

    @if ($discoveryOn)
        <nav class="store-discover" aria-label="Discovery">
            <div class="store-discover__inner">
                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']))
                        <a class="store-discover__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @endif
                @endforeach
                @foreach ($discoveryCategories as $category)
                    <a class="store-discover__link" href="{{ route('storefront.category', $category->slug) }}">{{ $category->name }}</a>
                @endforeach
            </div>
        </nav>
    @endif

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
                @foreach ($discoveryCategories as $category)
                    <a class="store-drawer__link" href="{{ route('storefront.category', $category->slug) }}" @click="navOpen = false">{{ $category->name }}</a>
                @endforeach
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

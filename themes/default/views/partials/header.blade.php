@php
    $cfg = $themeConfig ?? app(\App\Agovena\Theme\ThemeManager::class)->config();
    $announcementOn = $cfg->bool('header.announcement_enabled', true);
    $uspItems = $announcementOn ? $cfg->uspItems() : [];
    $searchOn = $cfg->bool('header.search_enabled', true);
    $showAccount = $cfg->bool('header.show_account', true);
    $categoriesOn = $cfg->bool('header.show_discovery_bar', true);
    $discoveryCategories = $discoveryCategories ?? collect();
    $suggestUrl = route('storefront.search.suggest');
    $brandingLogoUrl = $brandingLogoUrl ?? app(\App\Agovena\Theme\StorefrontBrand::class)->logoUrl();
    $navItems = collect($themeMainNav ?? [])
        ->filter(static fn (array $item): bool => ! empty($item['url']) && ! in_array(mb_strtolower($item['label']), ['shop', 'home', 'events'], true))
        ->values();
    $customNavSetting = strtolower(trim($cfg->string('header.custom_nav_items', '3')));
    $customNavCount = filter_var($customNavSetting, FILTER_VALIDATE_INT);
    if ($customNavSetting !== 'infinite' && ($customNavCount === false || $customNavCount < 0)) {
        $customNavCount = 3;
    }
    $desktopNavItems = $customNavSetting === 'infinite'
        ? $navItems
        : $navItems->take($customNavCount);
    // Normalised once: the desktop account menu and the mobile drawer both read it.
    $notificationUnreadCount = (int) ($notificationUnreadCount ?? 0);
@endphp

{{-- Header composition. Sections live in theme::partials.header.*; this file wires shared
     header state ($cfg flags, nav items, categories) into them. --}}
@include('theme::partials.header.usp-bar')

<header
    class="store-chrome"
    data-suggest-url="{{ $suggestUrl }}"
    data-search-base-url="{{ route('storefront.home') }}"
    data-suggest-query="{{ request('q', '') }}"
    data-searching-label="{{ __('storefront.search.searching') }}"
    data-no-matches-label="{{ __('storefront.search.no_matches') }}"
    data-view-all-label="{{ __('storefront.search.view_all') }}"
    x-data="storefrontHeader"
    @keydown.escape.window="closeAll()"
    @resize.window="handleResize()"
    @scroll.window.passive="scheduleDrawerTopRefresh()"
    x-effect="syncDrawerLock()"
>
    <div class="store-header">
        <div class="store-header__inner">
            <button
                type="button"
                class="store-header__menu"
                @click="toggleNav()"
                :aria-expanded="navOpen.toString()"
                x-bind:aria-label='navOpen ? {!! e(json_encode(__('storefront.close'))) !!} : {!! e(json_encode(__('storefront.menu'))) !!}'
                aria-controls="store-mobile-nav"
            >
                <span class="store-header__menu-bars" x-show="!navOpen" aria-hidden="true"></span>
                <svg class="store-header__menu-x" x-show="navOpen" x-cloak xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M18 6 6 18"/>
                    <path d="m6 6 12 12"/>
                </svg>
            </button>

            <a
                class="store-brand"
                href="{{ route('storefront.home') }}"
                x-data="storefrontBrand"
                :class="{ 'is-logo-ready': logoReady }"
            >
                <svg class="store-brand__fallback" x-show="!logoReady" width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <path d="M20 5 6.5 34h6.1l2.7-6.5h9.4l2.7 6.5h6.1L20 5Zm-2.4 17.2L20 13.8l2.4 8.4h-4.8Z" fill="currentColor"/>
                    <circle cx="6.5" cy="34" r="2" fill="currentColor"/>
                    <circle cx="33.5" cy="34" r="2" fill="currentColor"/>
                </svg>
                <img
                    x-ref="logo"
                    class="store-brand__logo"
                    src="{{ $brandingLogoUrl }}"
                    alt="{{ $siteName ?? __('storefront.shop') }}"
                    width="40"
                    height="40"
                    loading="eager"
                    decoding="sync"
                    fetchpriority="high"
                    @load="markLogoReady()"
                >
            </a>

            <nav class="store-nav" x-ref="desktopNav" aria-label="{{ __('storefront.primary_nav') }}">
                @include('theme::partials.header.category-menu')

                @foreach ($desktopNavItems as $item)
                    <a class="store-nav__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            @if ($searchOn)
                @include('theme::partials.header.search')
            @endif

            <div class="store-header__actions">
                @include('theme::partials.header-preferences')
                <a class="store-header__utility store-header__cart" href="{{ route('storefront.cart') }}" aria-label="{{ __('storefront.nav.cart') }}{{ ($cartCount ?? 0) > 0 ? ', '.trans_choice('storefront.cart.items', $cartCount, ['count' => $cartCount]) : '' }}">
                    @include('theme::partials.icon', ['name' => 'shopping-cart', 'size' => 20])
                    <span class="visually-hidden">{{ __('storefront.nav.cart') }}</span>
                    @if (($cartCount ?? 0) > 0)
                        <span class="store-header__cart-count" aria-hidden="true">{{ $cartCount }}</span>
                    @endif
                </a>
                @include('theme::partials.header.account')
            </div>

        @if ($searchOn)
            <div class="store-header__mobile-search">
                @include('theme::partials.header.search', ['mobile' => true])
            </div>
        @endif

        </div>
    </div>

    @include('theme::partials.header.drawer')
</header>

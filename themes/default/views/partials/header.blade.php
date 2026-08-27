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
@endphp

@if ($uspItems !== [])
    @php
        $uspBenefits = [];
        $uspCtas = [];
        foreach ($uspItems as $usp) {
            if ($usp['highlight']) {
                $uspCtas[] = $usp;
            } else {
                $uspBenefits[] = $usp;
            }
        }
    @endphp
    <div class="store-usp" role="region" aria-label="{{ __('storefront.benefits_aria') }}">
        <div class="store-usp__inner">
            @if ($uspBenefits !== [])
                <ul class="store-usp__benefits">
                    @foreach ($uspBenefits as $usp)
                        <li class="store-usp__item">
                            @if ($usp['href'] !== '')
                                <a class="store-usp__link" href="{{ $usp['href'] }}">
                                    @include('theme::partials.usp-label', ['usp' => $usp])
                                </a>
                            @else
                                <span class="store-usp__text">
                                    @include('theme::partials.usp-label', ['usp' => $usp])
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($uspCtas !== [])
                <ul class="store-usp__actions">
                    @foreach ($uspCtas as $usp)
                        @php
                            $ctaHref = $usp['href'] !== '' ? $usp['href'] : route('storefront.home');
                        @endphp
                        <li class="store-usp__item store-usp__item--cta">
                            <a class="store-usp__cta" href="{{ $ctaHref }}">
                                @include('theme::partials.usp-label', ['usp' => $usp])
                                <span class="store-usp__chev" aria-hidden="true">›</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif

<header
    class="store-chrome"
    x-data="{
        navOpen: false,
        drawerTop: 0,
        drawerObserver: null,
        drawerFrame: null,
        catsOpen: false,
        mobileCatsOpen: false,
        mobileCategoryOpen: null,
        mobileAccountOpen: false,
        activeCat: null,
        suggestOpen: false,
        suggestLoading: false,
        suggestItems: [],
        suggestQuery: @js(request('q', '')),
        suggestUrl: @js($suggestUrl),
        suggestTimer: null,
        labels: {
            searching: @js(__('storefront.search.searching')),
            noMatches: @js(__('storefront.search.no_matches')),
            viewAll: @js(__('storefront.search.view_all')),
        },
        updateDrawerTop() {
            const chromeParts = [
                document.querySelector('.store-usp'),
                document.querySelector('.store-header'),
                document.querySelector('.store-discover'),
            ].filter(Boolean);
            const bottom = chromeParts.reduce(
                (currentBottom, element) => Math.max(currentBottom, element.getBoundingClientRect().bottom),
                0,
            );
            this.drawerTop = Math.max(0, Math.round(bottom));
        },
        scheduleDrawerTopRefresh() {
            if (this.drawerFrame !== null) {
                return;
            }
            this.drawerFrame = requestAnimationFrame(() => {
                this.drawerFrame = null;
                this.updateDrawerTop();
            });
        },
        init() {
            this.$nextTick(() => {
                const refresh = () => this.updateDrawerTop();
                refresh();
                requestAnimationFrame(refresh);
                window.setTimeout(refresh, 200);
                if ('ResizeObserver' in window) {
                    this.drawerObserver = new ResizeObserver(refresh);
                    document.querySelectorAll('.store-usp, .store-header, .store-discover').forEach((element) => {
                        this.drawerObserver.observe(element);
                    });
                }
            });
        },
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
        },
        clearSuggest() {
            this.suggestQuery = '';
            this.suggestItems = [];
            this.suggestOpen = false;
            this.suggestLoading = false;
        }
    }"
    @keydown.escape.window="navOpen = false; catsOpen = false; mobileCatsOpen = false; mobileCategoryOpen = null; mobileAccountOpen = false; suggestOpen = false"
    @resize.window="scheduleDrawerTopRefresh()"
    @scroll.window.passive="scheduleDrawerTopRefresh()"
    x-effect="document.body.classList.toggle('store-drawer-open', navOpen)"
>
    <div class="store-header">
        <div class="store-header__inner">
            <button
                type="button"
                class="store-header__menu"
                @click="updateDrawerTop(); if (navOpen) { mobileCatsOpen = false; mobileCategoryOpen = null; mobileAccountOpen = false; } navOpen = !navOpen"
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

            <a class="store-brand" href="{{ route('storefront.home') }}">
                <img
                    class="store-brand__logo"
                    src="{{ $brandingLogoUrl }}"
                    alt="{{ $siteName ?? __('storefront.shop') }}"
                    width="160"
                    height="36"
                >
            </a>

            <nav class="store-nav" aria-label="{{ __('storefront.primary_nav') }}">
                @if ($categoriesOn && $discoveryCategories->isNotEmpty())
                    <div
                        class="store-cats"
                        @mouseenter="catsOpen = true"
                        @mouseleave="catsOpen = false; activeCat = null"
                        @focusin="catsOpen = true"
                        @click.outside="catsOpen = false; activeCat = null"
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
                                        @mouseenter="activeCat = {{ $category->id }}"
                                        :class="{ 'is-active': activeCat === {{ $category->id }} || (activeCat === null && {{ $loop->first ? 'true' : 'false' }}) }"
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
                                        x-show="activeCat === {{ $category->id }} || (activeCat === null && {{ $loop->first ? 'true' : 'false' }})"
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

                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']) && ! in_array(mb_strtolower($item['label']), ['shop', 'home'], true))
                        <a class="store-nav__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            @if ($searchOn)
                <div class="store-header__search-wrap" @click.outside="closeSuggest()">
                    <form class="store-header__search" action="{{ route('storefront.home') }}" method="get" role="search">
                        <label class="visually-hidden" for="store-header-search">{{ __('storefront.search.label') }}</label>
                        <input
                            id="store-header-search"
                            class="store-header__search-input"
                            type="search"
                            name="q"
                            x-model="suggestQuery"
                            @input="onSuggestInput()"
                            @focus="onSuggestInput()"
                            placeholder="{{ __('storefront.search.placeholder') }}"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-controls="store-search-suggest"
                        >
                        <button
                            type="button"
                            class="store-header__search-clear"
                            x-show="(suggestQuery || '').length > 0"
                            x-cloak
                            @click="clearSuggest()"
                            aria-label="{{ __('storefront.search.clear') }}"
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M18 6 6 18"/>
                                <path d="m6 6 12 12"/>
                            </svg>
                        </button>
                        <button type="submit" class="store-header__search-icon-btn" aria-label="{{ __('storefront.search.label') }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                        </button>
                    </form>
                    <div
                        id="store-search-suggest"
                        class="store-search-suggest"
                        x-show="suggestOpen"
                        x-cloak
                        @mousedown.prevent
                        role="listbox"
                        aria-label="{{ __('storefront.search.suggestions') }}"
                    >
                        <template x-if="suggestLoading">
                            <p class="store-search-suggest__status" x-text="labels.searching"></p>
                        </template>
                        <template x-if="!suggestLoading && suggestItems.length === 0 && (suggestQuery || '').trim().length >= 2">
                            <p class="store-search-suggest__status" x-text="labels.noMatches"></p>
                        </template>
                        <template x-for="item in suggestItems" :key="item.slug">
                            <a class="store-search-suggest__item" :href="item.url" role="option">
                                <span class="store-search-suggest__media" aria-hidden="true">
                                    <template x-if="item.image">
                                        <img :src="item.image" alt="">
                                    </template>
                                </span>
                                <span class="store-search-suggest__copy">
                                    <span class="store-search-suggest__name" x-text="item.name"></span>
                                    <span class="store-search-suggest__meta" x-text="item.category || ''"></span>
                                </span>
                                <span class="store-search-suggest__price" x-text="item.price"></span>
                            </a>
                        </template>
                        <a
                            class="store-search-suggest__all"
                            :href="'{{ route('storefront.home') }}?q=' + encodeURIComponent((suggestQuery || '').trim())"
                            x-show="(suggestQuery || '').trim().length >= 2"
                            x-text="labels.viewAll"
                        ></a>
                    </div>
                </div>
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
                @if ($showAccount)
                    @auth
                        @php
                            $accountUser = auth()->user();
                            $canOpenAdmin = $accountUser instanceof \App\Models\User && $accountUser->canAccessAdmin();
                            $notificationUnreadCount = (int) ($notificationUnreadCount ?? 0);
                        @endphp
                        <div
                            class="store-header__account"
                            x-data="{ open: false }"
                            @keydown.escape.window="if (open) { open = false; $refs.accountTrigger?.focus() }"
                            @click.outside="open = false"
                        >
                            <button
                                type="button"
                                x-ref="accountTrigger"
                                class="store-header__utility store-header__account-trigger"
                                id="store-account-menu-button"
                                @click="open = !open; if (open) { $nextTick(() => $refs.accountMenu?.querySelector('[role=menuitem]')?.focus()) }"
                                @keydown.enter.prevent="open = !open; if (open) { $nextTick(() => $refs.accountMenu?.querySelector('[role=menuitem]')?.focus()) }"
                                @keydown.space.prevent="open = !open; if (open) { $nextTick(() => $refs.accountMenu?.querySelector('[role=menuitem]')?.focus()) }"
                                :aria-expanded="open.toString()"
                                :class="{ 'is-open': open }"
                                aria-haspopup="menu"
                                aria-controls="store-account-menu"
                                aria-label="{{ __('storefront.nav.account_menu') }}{{ $notificationUnreadCount > 0 ? ', '.trans_choice('customer.notifications.unread_count', $notificationUnreadCount, ['count' => $notificationUnreadCount]) : '' }}"
                            >
                                @include('theme::partials.icon', ['name' => 'user', 'size' => 22, 'class' => 'store-icon store-header__account-icon'])
                                <span class="visually-hidden">{{ __('storefront.nav.account_menu') }}</span>
                                @if ($notificationUnreadCount > 0)
                                    <span class="store-header__account-count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                                @endif
                            </button>
                            <div
                                id="store-account-menu"
                                x-ref="accountMenu"
                                class="store-header__account-menu store-account-menu"
                                style="left: unset; right: 0;"
                                x-show="open"
                                x-cloak
                                x-transition:enter="store-account-menu-enter"
                                x-transition:enter-start="store-account-menu-enter-start"
                                x-transition:enter-end="store-account-menu-enter-end"
                                x-transition:leave="store-account-menu-leave"
                                x-transition:leave-start="store-account-menu-leave-start"
                                x-transition:leave-end="store-account-menu-leave-end"
                                role="menu"
                                aria-labelledby="store-account-menu-button"
                                @keydown.escape.stop="open = false; $refs.accountTrigger?.focus()"
                            >
                                @include('theme::partials.account-menu', [
                                    'accountUser' => $accountUser,
                                    'canOpenAdmin' => $canOpenAdmin,
                                    'notificationUnreadCount' => $notificationUnreadCount,
                                ])
                            </div>
                        </div>
                    @else
                        <div class="store-header__auth">
                            <a class="store-header__auth-link" href="{{ route('login') }}">{{ __('storefront.nav.login') }}</a>
                            <a class="store-header__auth-register" href="{{ route('register') }}">{{ __('storefront.nav.register') }}</a>
                        </div>
                    @endauth
                @endif
            </div>

        @if ($searchOn)
            <div class="store-header__mobile-search">
                <div class="store-header__search-wrap store-header__search-wrap--mobile" @click.outside="closeSuggest()">
                    <form class="store-header__search store-header__search--mobile" action="{{ route('storefront.home') }}" method="get" role="search">
                        <label class="visually-hidden" for="store-mobile-header-search">{{ __('storefront.search.label') }}</label>
                        <input
                            id="store-mobile-header-search"
                            class="store-header__search-input"
                            type="search"
                            name="q"
                            x-model="suggestQuery"
                            @input="onSuggestInput()"
                            @focus="onSuggestInput()"
                            placeholder="{{ __('storefront.search.placeholder') }}"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-controls="store-mobile-header-search-suggest"
                        >
                        <button
                            type="button"
                            class="store-header__search-clear"
                            x-show="(suggestQuery || '').length > 0"
                            x-cloak
                            @click="clearSuggest()"
                            aria-label="{{ __('storefront.search.clear') }}"
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M18 6 6 18"/>
                                <path d="m6 6 12 12"/>
                            </svg>
                        </button>
                        <button type="submit" class="store-header__search-icon-btn" aria-label="{{ __('storefront.search.label') }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                        </button>
                    </form>
                    <div
                        id="store-mobile-header-search-suggest"
                        class="store-search-suggest store-header__mobile-search-suggest"
                        x-show="suggestOpen"
                        x-cloak
                        @mousedown.prevent
                        role="listbox"
                        aria-label="{{ __('storefront.search.suggestions') }}"
                    >
                        <template x-if="suggestLoading">
                            <p class="store-search-suggest__status" x-text="labels.searching"></p>
                        </template>
                        <template x-if="!suggestLoading && suggestItems.length === 0 && (suggestQuery || '').trim().length >= 2">
                            <p class="store-search-suggest__status" x-text="labels.noMatches"></p>
                        </template>
                        <template x-for="item in suggestItems" :key="item.slug">
                            <a class="store-search-suggest__item" :href="item.url" role="option">
                                <span class="store-search-suggest__media" aria-hidden="true">
                                    <template x-if="item.image">
                                        <img :src="item.image" alt="">
                                    </template>
                                </span>
                                <span class="store-search-suggest__copy">
                                    <span class="store-search-suggest__name" x-text="item.name"></span>
                                    <span class="store-search-suggest__meta" x-text="item.category || ''"></span>
                                </span>
                                <span class="store-search-suggest__price" x-text="item.price"></span>
                            </a>
                        </template>
                        <a
                            class="store-search-suggest__all"
                            :href="'{{ route('storefront.home') }}?q=' + encodeURIComponent((suggestQuery || '').trim())"
                            x-show="(suggestQuery || '').trim().length >= 2"
                            x-text="labels.viewAll"
                        ></a>
                    </div>
                </div>
            </div>
        @endif

        </div>
    </div>

    <div
        id="store-mobile-nav"
        class="store-drawer"
        :style="'top: ' + drawerTop + 'px'"
        x-show="navOpen"
        x-bind:hidden="!navOpen"
        x-cloak
        x-transition:enter="store-drawer--enter"
        x-transition:enter-start="store-drawer--enter-start"
        x-transition:enter-end="store-drawer--enter-end"
        x-transition:leave="store-drawer--leave"
        x-transition:leave-start="store-drawer--leave-start"
        x-transition:leave-end="store-drawer--leave-end"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('storefront.menu') }}"
    >
        <div class="store-drawer__backdrop" @click="navOpen = false; mobileCatsOpen = false; mobileCategoryOpen = null; mobileAccountOpen = false; closeSuggest()"></div>
        <div
            class="store-drawer__panel"
            x-transition:enter="store-drawer__panel--enter"
            x-transition:enter-start="store-drawer__panel--enter-start"
            x-transition:enter-end="store-drawer__panel--enter-end"
            x-transition:leave="store-drawer__panel--leave"
            x-transition:leave-start="store-drawer__panel--leave-start"
            x-transition:leave-end="store-drawer__panel--leave-end"
        >
            <div class="store-drawer__head">
                <p class="store-drawer__title">{{ __('storefront.menu') }}</p>
            </div>
            <div class="store-drawer__preferences">
                <p class="store-drawer__section-label">{{ __('storefront.preferences.aria') }}</p>
                @include('theme::partials.header-preferences', ['isMobile' => true])
            </div>
            <nav class="store-drawer__nav" aria-label="{{ __('storefront.mobile_nav') }}">
                @if ($categoriesOn && $discoveryCategories->isNotEmpty())
                    <div class="store-drawer__categories" :class="{ 'is-open': mobileCatsOpen }">
                        <button
                            type="button"
                            class="store-nav__link store-nav__link--btn store-drawer__primary-link"
                            @click="mobileCatsOpen = !mobileCatsOpen; if (!mobileCatsOpen) mobileCategoryOpen = null"
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
                            <a class="store-drawer__category-all" href="{{ route('storefront.categories') }}" @click="navOpen = false">{{ __('storefront.nav.all_categories') }}</a>
                            @foreach ($discoveryCategories as $category)
                                <div class="store-drawer__category-group">
                                    <div class="store-drawer__category-row" :class="{ 'is-open': mobileCategoryOpen === {{ $category->id }} }">
                                        <a class="store-drawer__category-root" href="{{ route('storefront.category', $category->slug) }}" @click="navOpen = false">
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
                                                @click="mobileCategoryOpen = mobileCategoryOpen === {{ $category->id }} ? null : {{ $category->id }}"
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
                                                <a href="{{ route('storefront.category', $child->slug) }}" @click="navOpen = false">{{ $child->name }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']) && ! in_array(mb_strtolower($item['label']), ['shop', 'home'], true))
                        <a class="store-nav__link store-drawer__primary-link" href="{{ $item['url'] }}" @click="navOpen = false">{{ $item['label'] }}</a>
                    @endif
                @endforeach
                @if ($showAccount)
                    @auth
                        @php
                            $drawerAccountUser = auth()->user();
                            $drawerCanAdmin = $drawerAccountUser instanceof \App\Models\User && $drawerAccountUser->canAccessAdmin();
                            $drawerAccountName = $drawerAccountUser instanceof \App\Models\User && filled($drawerAccountUser->name)
                                ? $drawerAccountUser->name
                                : __('storefront.nav.account');
                        @endphp
                        <div class="store-drawer__account" :class="{ 'is-open': mobileAccountOpen }">
                            <button
                                type="button"
                                class="store-nav__link store-nav__link--btn store-drawer__primary-link store-drawer__account-toggle"
                                @click="mobileAccountOpen = !mobileAccountOpen"
                                :aria-expanded="mobileAccountOpen.toString()"
                                aria-controls="store-mobile-account"
                                aria-haspopup="true"
                            >
                                <span class="store-drawer__account-identity">
                                    <span class="store-drawer__account-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'user', 'size' => 18])</span>
                                    <span class="store-drawer__account-name">{{ $drawerAccountName }}</span>
                                </span>
                                <svg class="store-drawer__account-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div
                                id="store-mobile-account"
                                class="store-drawer__account-panel"
                                x-show="mobileAccountOpen"
                                x-bind:hidden="!mobileAccountOpen"
                                x-cloak
                                x-transition:enter="store-drawer__account-panel--enter"
                                x-transition:enter-start="store-drawer__account-panel--enter-start"
                                x-transition:enter-end="store-drawer__account-panel--enter-end"
                                x-transition:leave="store-drawer__account-panel--leave"
                                x-transition:leave-start="store-drawer__account-panel--leave-start"
                                x-transition:leave-end="store-drawer__account-panel--leave-end"
                                role="region"
                                aria-label="{{ __('storefront.nav.account') }}"
                            >
                                <a class="store-drawer__link" href="{{ route('customer.account') }}" @click="navOpen = false">
                                    <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'layout-dashboard', 'size' => 18])</span>
                                    <span class="store-drawer__link-text">{{ __('storefront.nav.dashboard') }}</span>
                                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                                </a>
                                <a class="store-drawer__link" href="{{ route('customer.profile') }}" @click="navOpen = false">
                                    <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'user', 'size' => 18])</span>
                                    <span class="store-drawer__link-text">{{ __('storefront.nav.account') }}</span>
                                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                                </a>
                                <a
                                    class="store-drawer__link store-drawer__link--notifications"
                                    href="{{ route('customer.notifications') }}"
                                    @click="navOpen = false"
                                    aria-label="{{ __('customer.notifications.title') }}{{ ($notificationUnreadCount ?? 0) > 0 ? ', '.trans_choice('customer.notifications.unread_count', $notificationUnreadCount, ['count' => $notificationUnreadCount]) : '' }}"
                                >
                                    <span class="store-drawer__link-icon store-drawer__notification-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'bell', 'size' => 18])</span>
                                    <span class="store-drawer__link-text">{{ __('customer.notifications.title') }}</span>
                                    @if (($notificationUnreadCount ?? 0) > 0)
                                        <span class="store-drawer__count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                                    @endif
                                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                                </a>
                                @if ($drawerCanAdmin)
                                    <a class="store-drawer__link" href="{{ route('admin.dashboard') }}" @click="navOpen = false">
                                        <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'settings', 'size' => 18])</span>
                                        <span class="store-drawer__link-text">{{ __('storefront.nav.admin') }}</span>
                                        <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('customer.logout') }}" class="store-drawer__logout-form" @submit="navOpen = false">
                                    @csrf
                                    <button type="submit" class="store-drawer__link store-drawer__link--danger">
                                        <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'log-out', 'size' => 18])</span>
                                        <span class="store-drawer__link-text">{{ __('storefront.nav.logout') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="store-drawer__auth">
                            <a class="store-btn store-btn--ghost store-drawer__auth-login" href="{{ route('login') }}" @click="navOpen = false">{{ __('storefront.nav.login') }}</a>
                            <a class="store-btn store-btn--primary store-drawer__auth-register" href="{{ route('register') }}" @click="navOpen = false">{{ __('storefront.nav.register') }}</a>
                        </div>
                    @endauth
                @endif
            </nav>
        </div>
    </div>
</header>

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
        catsOpen: false,
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
            const header = this.$root.querySelector('.store-header');
            this.drawerTop = header ? Math.round(header.getBoundingClientRect().bottom) : 0;
        },
        init() {
            this.$nextTick(() => this.updateDrawerTop());
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
    @keydown.escape.window="navOpen = false; catsOpen = false; suggestOpen = false"
    @resize.window="updateDrawerTop()"
>
    <div class="store-header">
        <div class="store-header__inner">
            <button
                type="button"
                class="store-header__menu"
                @click="updateDrawerTop(); navOpen = !navOpen"
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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
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
                                aria-label="{{ __('storefront.nav.account_menu') }}"
                            >
                                @include('theme::partials.icon', ['name' => 'user', 'size' => 20])
                                <span class="visually-hidden">{{ __('storefront.nav.account_menu') }}</span>
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
        </div>

    </div>

    <div
        id="store-mobile-nav"
        class="store-drawer"
        :style="'top: ' + drawerTop + 'px'"
        x-show="navOpen"
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
        <div class="store-drawer__backdrop" @click="navOpen = false; closeSuggest()"></div>
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
            @if ($searchOn)
                <div class="store-drawer__search-wrap" @click.outside="closeSuggest()">
                    <form class="store-header__search store-header__search--mobile" action="{{ route('storefront.home') }}" method="get" role="search">
                        <label class="visually-hidden" for="store-drawer-search">{{ __('storefront.search.label') }}</label>
                        <input
                            id="store-drawer-search"
                            class="store-header__search-input"
                            type="search"
                            name="q"
                            x-model="suggestQuery"
                            @input="onSuggestInput()"
                            @focus="onSuggestInput()"
                            placeholder="{{ __('storefront.search.placeholder') }}"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-controls="store-drawer-search-suggest"
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
                        id="store-drawer-search-suggest"
                        class="store-search-suggest store-drawer__search-suggest"
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
            <nav class="store-drawer__nav" aria-label="{{ __('storefront.mobile_nav') }}">
                @if ($categoriesOn)
                    <a class="store-drawer__link" href="{{ route('storefront.categories') }}" @click="navOpen = false">{{ __('storefront.nav.categories') }}</a>
                @endif
                @foreach ($themeMainNav ?? [] as $item)
                    @if (! empty($item['url']) && ! in_array(mb_strtolower($item['label']), ['shop', 'home'], true))
                        <a class="store-drawer__link" href="{{ $item['url'] }}" @click="navOpen = false">{{ $item['label'] }}</a>
                    @endif
                @endforeach
                @if ($categoriesOn && $discoveryCategories->isNotEmpty())
                    <p class="store-drawer__label">{{ __('storefront.nav.browse_categories') }}</p>
                    @foreach ($discoveryCategories as $category)
                        <a class="store-drawer__link" href="{{ route('storefront.category', $category->slug) }}" @click="navOpen = false">{{ $category->name }}</a>
                        @foreach ($category->children as $child)
                            <a class="store-drawer__link store-drawer__link--child" href="{{ route('storefront.category', $child->slug) }}" @click="navOpen = false">{{ $child->name }}</a>
                        @endforeach
                    @endforeach
                @endif
                <a class="store-drawer__link" href="{{ route('storefront.cart') }}" @click="navOpen = false">
                    {{ __('storefront.nav.cart') }}
                    @if (($cartCount ?? 0) > 0)
                        ({{ $cartCount }})
                    @endif
                </a>
                @if ($showAccount)
                    @auth
                        @php $drawerCanAdmin = auth()->user() instanceof \App\Models\User && auth()->user()->canAccessAdmin(); @endphp
                        <p class="store-drawer__label">{{ __('storefront.nav.account') }}</p>
                        <a class="store-drawer__link" href="{{ route('customer.account') }}" @click="navOpen = false">{{ __('storefront.nav.dashboard') }}</a>
                        <a class="store-drawer__link" href="{{ route('customer.profile') }}" @click="navOpen = false">{{ __('storefront.nav.account') }}</a>
                        @if ($drawerCanAdmin)
                            <a class="store-drawer__link" href="{{ route('admin.dashboard') }}" @click="navOpen = false">{{ __('storefront.nav.admin') }}</a>
                        @endif
                        <a class="store-drawer__link store-drawer__link--danger" href="{{ route('customer.logout') }}" @click="navOpen = false">{{ __('storefront.nav.logout') }}</a>
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

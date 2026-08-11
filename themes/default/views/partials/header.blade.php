<header class="store-header" data-sticky>
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

        <nav class="store-nav store-nav--desktop" aria-label="Primary">
            @foreach ($themeMainNav ?? [] as $item)
                <a class="store-nav__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="store-header__actions">
            <button
                type="button"
                class="store-header__action"
                @click="searchOpen = !searchOpen"
                :aria-expanded="searchOpen.toString()"
                aria-controls="store-search"
            >
                Search
            </button>
            <span
                class="store-header__action store-header__action--muted"
                title="Customer accounts will be available when the customer portal ships"
            >
                Account
            </span>
            <a class="store-header__cart" href="{{ route('storefront.cart') }}">
                Cart
                @if (($cartCount ?? 0) > 0)
                    <span class="store-header__cart-count" aria-label="{{ $cartCount }} items in cart">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div
        id="store-search"
        class="store-search"
        x-show="searchOpen"
        x-cloak
        x-transition
        @click.outside="searchOpen = false"
    >
        <form class="store-search__form" action="{{ route('storefront.home') }}" method="get" role="search">
            <label class="visually-hidden" for="store-search-input">Search products</label>
            <input
                id="store-search-input"
                class="store-search__input"
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search products"
                autocomplete="off"
            >
            <button type="submit" class="store-btn store-btn--primary">Search</button>
        </form>
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
                    <a class="store-drawer__link" href="{{ $item['url'] }}" @click="navOpen = false">{{ $item['label'] }}</a>
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

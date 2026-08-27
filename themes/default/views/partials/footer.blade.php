@php
    $cfg = $themeConfig ?? app(\App\Agovena\Theme\ThemeManager::class)->config();
    $tagline = $cfg->string('footer.tagline', 'Quality products, clear pricing, and a simple shopping experience.');
    $brandingLogoUrl = $brandingLogoUrl ?? app(\App\Agovena\Theme\StorefrontBrand::class)->logoUrl();
    $storeName = $siteName ?? __('storefront.shop');
    $categoriesOn = $cfg->bool('header.show_discovery_bar', true);
    $showAccount = $cfg->bool('header.show_account', true);
    $footerNav = collect($themeFooterNav ?? [])->filter(fn ($item) => ! empty($item['url']))->values();
    $legalNav = collect($themeLegalNav ?? [])->filter(fn ($item) => ! empty($item['url']))->values();
    $coreAccountRoutes = ['customer.account', 'customer.profile', 'customer.orders.index'];
    $accountNavItems = collect($customerAccountNavItems ?? [])
        ->filter(fn ($item) => filled($item->route ?? null) && ! in_array($item->route, $coreAccountRoutes, true))
        ->values();
@endphp

<footer class="store-footer" role="contentinfo">
    <div class="store-footer__inner">
        <div class="store-footer__brand">
            <a class="store-footer__brand-link" href="{{ route('storefront.home') }}">
                <img class="store-footer__logo" src="{{ $brandingLogoUrl }}" alt="" width="40" height="40">
                <span class="store-footer__name">{{ $storeName }}</span>
            </a>
            @if ($tagline !== '')
                <p class="store-footer__tagline">{{ $tagline }}</p>
            @endif
        </div>

        <nav class="store-footer__col" aria-labelledby="store-footer-shop-heading">
            <p id="store-footer-shop-heading" class="store-footer__heading">{{ __('storefront.footer.shop') }}</p>
            <ul class="store-footer__list" role="list">
                <li><a href="{{ route('storefront.home') }}#catalog">{{ __('storefront.nav.shop_products') }}</a></li>
                @if ($categoriesOn)
                    <li><a href="{{ route('storefront.categories') }}">{{ __('storefront.nav.categories') }}</a></li>
                @endif
                <li><a href="{{ route('storefront.cart') }}">{{ __('storefront.nav.cart') }}</a></li>
                @foreach ($footerNav as $item)
                    @continue(in_array(mb_strtolower((string) $item['label']), ['cart', 'winkelwagen', 'categories', 'categorieën', 'shop'], true))
                    <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($showAccount)
            <nav class="store-footer__col" aria-labelledby="store-footer-account-heading">
                <p id="store-footer-account-heading" class="store-footer__heading">{{ __('storefront.footer.account') }}</p>
                <ul class="store-footer__list" role="list">
                    @auth
                        <li><a href="{{ route('customer.account') }}">{{ __('storefront.nav.dashboard') }}</a></li>
                        <li><a href="{{ route('customer.orders.index') }}">{{ __('storefront.nav.orders') }}</a></li>
                        <li><a href="{{ route('customer.profile') }}">{{ __('storefront.nav.account') }}</a></li>
                        @foreach ($accountNavItems as $navItem)
                            <li>
                                <a href="{{ route($navItem->route) }}">{{ __($navItem->label) }}</a>
                            </li>
                        @endforeach
                    @else
                        <li><a href="{{ route('login') }}">{{ __('storefront.nav.login') }}</a></li>
                        <li><a href="{{ route('register') }}">{{ __('storefront.nav.register') }}</a></li>
                    @endauth
                </ul>
            </nav>
        @endif

        @if ($legalNav->isNotEmpty())
            <nav class="store-footer__col" aria-labelledby="store-footer-legal-heading">
                <p id="store-footer-legal-heading" class="store-footer__heading">{{ __('storefront.footer.legal') }}</p>
                <ul class="store-footer__list" role="list">
                    @foreach ($legalNav as $item)
                        <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                    @endforeach
                    <li>
                        <button type="button" class="store-footer__inline-btn" data-cookie-open>
                            {{ __('storefront.cookie_consent.settings_link') }}
                        </button>
                    </li>
                </ul>
            </nav>
        @else
            <nav class="store-footer__col" aria-labelledby="store-footer-legal-heading">
                <p id="store-footer-legal-heading" class="store-footer__heading">{{ __('storefront.footer.legal') }}</p>
                <ul class="store-footer__list" role="list">
                    <li>
                        <button type="button" class="store-footer__inline-btn" data-cookie-open>
                            {{ __('storefront.cookie_consent.settings_link') }}
                        </button>
                    </li>
                </ul>
            </nav>
        @endif
    </div>

    <div class="store-footer__bottom">
        <p class="store-footer__copy">&copy; {{ now()->year }} {{ $storeName }}. {{ __('storefront.footer.rights') }}</p>
        @if ($legalNav->isNotEmpty())
            <ul class="store-footer__legal-inline" role="list">
                @foreach ($legalNav as $item)
                    <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>
</footer>

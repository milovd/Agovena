<header class="store-checkout-chrome">
    <div class="store-checkout-chrome__inner">
        <a class="store-brand" href="{{ route('storefront.home') }}">
            @if (! empty($brandingLogoPath))
                <img
                    class="store-brand__logo"
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingLogoPath) }}"
                    alt="{{ $siteName ?? __('storefront.shop') }}"
                >
            @else
                <span class="store-brand__name">{{ $siteName ?? __('storefront.shop') }}</span>
            @endif
        </a>
        <a class="store-checkout-chrome__back" href="{{ route('storefront.cart') }}">{{ __('storefront.checkout.back_to_cart') }}</a>
        @auth
            <a class="store-checkout-chrome__account" href="{{ route('customer.account') }}">{{ __('storefront.nav.account') }}</a>
        @else
            <a class="store-checkout-chrome__account" href="{{ route('login') }}">{{ __('storefront.nav.login') }}</a>
        @endauth
    </div>
</header>

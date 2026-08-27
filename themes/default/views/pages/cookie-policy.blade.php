<article class="store-page">
    <nav class="store-breadcrumbs" aria-label="{{ __('storefront.breadcrumb_aria') }}">
        <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ __('storefront.cookie_consent.policy') }}</span>
    </nav>

    <h1 class="store-title">{{ __('storefront.cookie_consent.policy') }}</h1>

    <div class="store-page__body">
        <p>{{ __('storefront.cookie_consent.lead') }}</p>

        <h2>{{ __('storefront.cookie_consent.essential') }}</h2>
        <p>{{ __('storefront.cookie_consent.essential_description') }}</p>

        <h2>{{ __('storefront.cookie_consent.analytics') }}</h2>
        <p>{{ __('storefront.cookie_consent.analytics_description') }}</p>

        <p>
            <button type="button" class="store-btn store-btn--primary" data-cookie-open>
                {{ __('storefront.cookie_consent.customize') }}
            </button>
        </p>
    </div>
</article>

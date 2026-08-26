<aside class="store-cookie-consent" role="dialog" aria-labelledby="cookie-consent-title" aria-describedby="cookie-consent-description">
    <div class="store-cookie-consent__inner">
        <div class="store-cookie-consent__copy">
            <h2 id="cookie-consent-title" class="store-cookie-consent__title">{{ __('storefront.cookie_consent.title') }}</h2>
            <p id="cookie-consent-description" class="store-cookie-consent__text">{{ __('storefront.cookie_consent.description') }}</p>
        </div>
        <div class="store-cookie-consent__actions">
            <form method="post" action="{{ route('privacy.consent') }}">
                @csrf
                <input type="hidden" name="choice" value="necessary">
                <button type="submit" class="store-btn store-btn--outline">{{ __('storefront.cookie_consent.necessary_only') }}</button>
            </form>
            <form method="post" action="{{ route('privacy.consent') }}">
                @csrf
                <input type="hidden" name="choice" value="all">
                <button type="submit" class="store-btn store-btn--primary">{{ __('storefront.cookie_consent.accept_all') }}</button>
            </form>
        </div>
    </div>
</aside>

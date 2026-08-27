@php
    $consentCookie = request()->cookie(\App\Agovena\Privacy\RecordCookieConsent::COOKIE_NAME);
    $consent = is_string($consentCookie) ? json_decode($consentCookie, true) : null;
    $hasConsent = is_array($consent) && ($consent['version'] ?? null) === '1';
    $analyticsEnabled = $hasConsent && ($consent['categories']['analytics'] ?? false) === true;
    $currentConsentId = $hasConsent && isset($consent['id']) ? (string) $consent['id'] : null;
    $currentConsentDate = $hasConsent && isset($consent['date']) ? (string) $consent['date'] : null;
@endphp

<div
    class="store-cookie-consent"
    data-cookie-banner
    data-cookie-endpoint="{{ route('privacy.consent') }}"
    data-cookie-has-consent="{{ $hasConsent ? 'true' : 'false' }}"
    role="presentation"
    @if ($hasConsent) hidden @endif
>
    <div
        class="store-cookie-consent__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cookie-banner-title"
        aria-describedby="cookie-banner-description"
        tabindex="-1"
        data-cookie-banner-dialog
    >
        <p class="store-cookie-consent__eyebrow">{{ __('storefront.cookie_consent.eyebrow') }}</p>
        <h2 id="cookie-banner-title" class="store-cookie-consent__title">{{ __('storefront.cookie_consent.title') }}</h2>
        <p id="cookie-banner-description" class="store-cookie-consent__lead">{{ __('storefront.cookie_consent.lead') }}</p>
        <p class="store-cookie-consent__links">
            <a class="store-cookie-consent__policy" href="{{ url('/cookies') }}">{{ __('storefront.cookie_consent.policy') }}</a>
            <span aria-hidden="true">·</span>
            <a class="store-cookie-consent__policy" href="{{ url('/privacy') }}">{{ __('storefront.cookie_consent.privacy') }}</a>
        </p>
        <div class="store-cookie-consent__actions">
            <form method="post" action="{{ route('privacy.consent') }}" data-cookie-choice-form data-cookie-choice="necessary">
                @csrf
                <button type="submit" class="store-btn store-btn--outline" data-cookie-reject>
                    {{ __('storefront.cookie_consent.reject') }}
                </button>
            </form>
            <button type="button" class="store-btn store-btn--outline" data-cookie-settings>
                {{ __('storefront.cookie_consent.customize') }}
            </button>
            <form method="post" action="{{ route('privacy.consent') }}" data-cookie-choice-form data-cookie-choice="analytics">
                @csrf
                <button type="submit" class="store-btn store-btn--primary" data-cookie-accept>
                    {{ __('storefront.cookie_consent.accept') }}
                </button>
            </form>
        </div>
        <p class="store-cookie-consent__error" data-cookie-error role="alert" hidden>
            {{ __('storefront.cookie_consent.save_error') }}
        </p>
    </div>
</div>

<div class="store-cookie-panel" data-cookie-panel hidden>
    <div
        class="store-cookie-panel__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cookie-settings-title"
        tabindex="-1"
        data-cookie-dialog
    >
        <div class="store-cookie-panel__head">
            <div>
                <p class="store-cookie-consent__eyebrow">{{ __('storefront.cookie_consent.eyebrow') }}</p>
                <h2 id="cookie-settings-title">{{ __('storefront.cookie_consent.customize') }}</h2>
            </div>
            <button
                type="button"
                class="store-cookie-panel__close"
                data-cookie-close
                aria-label="{{ __('storefront.cookie_consent.close') }}"
            >
                <svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true">
                    <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="store-cookie-tabs" role="tablist" aria-label="{{ __('storefront.cookie_consent.customize') }}">
            <button
                type="button"
                class="store-cookie-tabs__btn is-active"
                role="tab"
                aria-selected="true"
                aria-controls="cookie-pane-consent"
                data-cookie-tab="consent"
            >
                {{ __('storefront.cookie_consent.tab_consent') }}
            </button>
            <button
                type="button"
                class="store-cookie-tabs__btn"
                role="tab"
                aria-selected="false"
                aria-controls="cookie-pane-about"
                data-cookie-tab="about"
            >
                {{ __('storefront.cookie_consent.tab_about') }}
            </button>
        </div>

        <div class="store-cookie-panel__body">
            <div
                id="cookie-pane-consent"
                class="store-cookie-panel__pane is-active"
                data-cookie-pane="consent"
                role="tabpanel"
            >
                <div class="store-cookie-panel__list">
                    <div class="store-cookie-panel__row">
                        <div class="store-cookie-panel__copy">
                            <strong id="cookie-essential-label">{{ __('storefront.cookie_consent.essential') }}</strong>
                            <p>{{ __('storefront.cookie_consent.essential_description') }}</p>
                            <p class="store-cookie-panel__locked-msg" data-essential-msg hidden>
                                {{ __('storefront.cookie_consent.essential_locked') }}
                            </p>
                        </div>
                        <label class="store-cookie-toggle store-cookie-toggle--locked">
                            <input
                                type="checkbox"
                                checked
                                aria-disabled="true"
                                aria-labelledby="cookie-essential-label"
                                data-cookie-essential
                            >
                            <span class="store-cookie-toggle__track" aria-hidden="true">
                                <span class="store-cookie-toggle__thumb"></span>
                            </span>
                        </label>
                    </div>

                    <div class="store-cookie-panel__row">
                        <div class="store-cookie-panel__copy">
                            <strong id="cookie-analytics-label">{{ __('storefront.cookie_consent.analytics') }}</strong>
                            <p>{{ __('storefront.cookie_consent.analytics_description') }}</p>
                        </div>
                        <label class="store-cookie-toggle">
                            <input
                                type="checkbox"
                                @checked($analyticsEnabled)
                                aria-labelledby="cookie-analytics-label"
                                data-cookie-analytics
                            >
                            <span class="store-cookie-toggle__track" aria-hidden="true">
                                <span class="store-cookie-toggle__thumb"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="store-cookie-panel__actions">
                    <button type="button" class="store-btn store-btn--outline" data-cookie-reject-panel>
                        {{ __('storefront.cookie_consent.reject') }}
                    </button>
                    <button type="button" class="store-btn store-btn--primary" data-cookie-save>
                        {{ __('storefront.cookie_consent.save') }}
                    </button>
                </div>
            </div>

            <div
                id="cookie-pane-about"
                class="store-cookie-panel__pane"
                data-cookie-pane="about"
                role="tabpanel"
                hidden
            >
                <div class="store-cookie-about">
                    @foreach ((array) __('storefront.cookie_consent.about_paragraphs') as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                    <p>
                        <a class="store-cookie-consent__policy" href="{{ url('/privacy') }}">{{ __('storefront.cookie_consent.privacy') }}</a>
                        <span aria-hidden="true"> · </span>
                        <a class="store-cookie-consent__policy" href="{{ url('/cookies') }}">{{ __('storefront.cookie_consent.policy') }}</a>
                    </p>
                    <p
                        class="store-cookie-about__meta"
                        data-consent-meta
                        data-consent-empty="{{ __('storefront.cookie_consent.consent_meta_empty') }}"
                        data-consent-template="{{ __('storefront.cookie_consent.consent_meta', ['id' => ':id', 'date' => ':date']) }}"
                    >
                        @if ($currentConsentId !== null && $currentConsentDate !== null)
                            {{ __('storefront.cookie_consent.consent_meta', ['id' => $currentConsentId, 'date' => $currentConsentDate]) }}
                        @else
                            {{ __('storefront.cookie_consent.consent_meta_empty') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <p class="store-cookie-panel__error" data-cookie-error role="alert" hidden>
            {{ __('storefront.cookie_consent.save_error') }}
        </p>
    </div>
</div>

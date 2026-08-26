@php
    $locales = $storefrontLocales ?? config('agovena.locales', ['en' => 'English']);
    $currentLocale = $storefrontLocale ?? app()->getLocale();
    $currencies = collect($storefrontCurrencies ?? []);
    $currentCurrency = $storefrontCurrency ?? 'EUR';
    $showLocale = count($locales) > 1;
    $showCurrency = $currencies->count() > 1;
    $showRegion = $showLocale || $showCurrency;
    $isMobile = $isMobile ?? false;
    $preferencesClass = $isMobile ? 'store-drawer__prefs' : 'store-header__prefs';
    $regionMenuId = $isMobile ? 'store-region-menu-mobile' : 'store-region-menu';

    $regionLabel = $showCurrency ? (string) $currentCurrency : '';
@endphp

<div class="{{ $preferencesClass }}" aria-label="{{ __('storefront.preferences.aria') }}">
    @if ($showRegion)
        <div
            class="store-header__region"
            x-data="{ open: false }"
            @keydown.escape.window="if (open) { open = false; $refs.regionTrigger?.focus() }"
            @click.outside="open = false"
        >
            <button
                type="button"
                x-ref="regionTrigger"
                class="store-header__region-trigger"
                @click="open = !open"
                :aria-expanded="open.toString()"
                :class="{ 'is-open': open }"
                aria-haspopup="dialog"
                aria-controls="{{ $regionMenuId }}"
                aria-label="{{ __('storefront.preferences.region') }}"
            >
                <span class="store-header__flag" aria-hidden="true">
                    <x-ag.flag :code="$currentLocale" :width="18" />
                </span>
                @if ($regionLabel !== '')
                    <span class="store-header__region-sep" aria-hidden="true">|</span>
                    <span class="store-header__region-label">{{ $regionLabel }}</span>
                @endif
                <x-ag.icon name="chevron-down" class="store-header__region-chevron" :size="14" />
            </button>

            <div
                id="{{ $regionMenuId }}"
                class="store-header__region-menu"
                x-show="open"
                x-cloak
                role="dialog"
                aria-label="{{ __('storefront.preferences.region') }}"
            >
                @if ($showLocale)
                    <div class="store-header__region-section">
                        <p class="store-header__region-heading">{{ __('storefront.preferences.language') }}</p>
                        <div class="store-header__region-list" role="listbox" aria-label="{{ __('storefront.preferences.language') }}">
                            @foreach ($locales as $code => $label)
                                <form method="post" action="{{ route('storefront.preferences.locale') }}">
                                    @csrf
                                    <input type="hidden" name="locale" value="{{ $code }}">
                                    <button
                                        type="submit"
                                        class="store-header__region-option {{ $code === $currentLocale ? 'is-active' : '' }}"
                                        role="option"
                                        @if ($code === $currentLocale) aria-selected="true" @endif
                                    >
                                        <span class="store-header__flag" aria-hidden="true">
                                            <x-ag.flag :code="$code" :width="18" />
                                        </span>
                                        <span class="store-header__region-option-title">{{ $label }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($showLocale && $showCurrency)
                    <div class="store-header__region-divider" role="separator"></div>
                @endif

                @if ($showCurrency)
                    <div class="store-header__region-section">
                        <p class="store-header__region-heading">{{ __('storefront.preferences.currency') }}</p>
                        <div class="store-header__region-list" role="listbox" aria-label="{{ __('storefront.preferences.currency') }}">
                            @foreach ($currencies as $currency)
                                <form method="post" action="{{ route('storefront.preferences.currency') }}">
                                    @csrf
                                    <input type="hidden" name="currency" value="{{ $currency->code }}">
                                    @php
                                        $currencySymbol = trim($currency->prefix !== '' ? $currency->prefix : $currency->suffix);
                                        if ($currencySymbol === '') {
                                            $currencySymbol = $currency->code;
                                        }
                                    @endphp
                                    <button
                                        type="submit"
                                        class="store-header__region-option {{ $currency->code === $currentCurrency ? 'is-active' : '' }}"
                                        role="option"
                                        @if ($currency->code === $currentCurrency) aria-selected="true" @endif
                                    >
                                        <span class="store-header__currency-symbol" aria-hidden="true">{{ $currencySymbol }}</span>
                                        <span class="store-header__region-option-title">{{ $currency->name }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div
        class="store-header__theme"
        x-data="{
            theme: (localStorage.getItem('agovena.theme') || @js(app(\App\Agovena\Theme\ThemeManager::class)->config()->string('appearance.default_color_mode', 'system'))),
            init() {
                if (this.theme !== 'dark' && this.theme !== 'light') {
                    this.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                this.apply(this.theme);
            },
            apply(next) {
                this.theme = next === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', this.theme);
                localStorage.setItem('agovena.theme', this.theme);
            },
            toggle() { this.apply(this.theme === 'dark' ? 'light' : 'dark'); }
        }"
    >
        <button
            type="button"
            class="store-header__utility store-header__theme-toggle"
            @click="toggle()"
            :aria-label="theme === 'dark' ? '{{ __('storefront.preferences.theme_to_light') }}' : '{{ __('storefront.preferences.theme_to_dark') }}'"
            :title="theme === 'dark' ? '{{ __('storefront.preferences.theme_to_light') }}' : '{{ __('storefront.preferences.theme_to_dark') }}'"
        >
            <span class="store-header__theme-icon" x-show="theme !== 'dark'" x-cloak aria-hidden="true">
                <x-ag.icon name="moon" :size="20" />
            </span>
            <span class="store-header__theme-icon" x-show="theme === 'dark'" x-cloak aria-hidden="true">
                <x-ag.icon name="sun" :size="20" />
            </span>
        </button>
    </div>
</div>

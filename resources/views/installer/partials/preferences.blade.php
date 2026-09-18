@php
    $installerLocales = config('agovena.locales', ['en' => 'English']);
    $installerLocale = app()->getLocale();
@endphp

<div class="install-shell__preferences" aria-label="{{ __('storefront.preferences.aria') }}">
    <details class="install-locale">
        <summary class="install-control install-locale__trigger">
            <span class="install-locale__flag" aria-hidden="true"><x-ag.flag :code="$installerLocale" :width="18" /></span>
            <span>{{ strtoupper($installerLocale) }}</span>
            <x-ag.icon name="chevron-down" :size="14" aria-hidden="true" />
        </summary>
        <div class="install-locale__menu" role="menu" aria-label="{{ __('storefront.preferences.language') }}">
            @foreach ($installerLocales as $code => $label)
                <form method="post" action="{{ route('installer.preferences.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit" class="install-locale__option {{ $code === $installerLocale ? 'is-active' : '' }}" role="menuitem">
                        <span class="install-locale__flag" aria-hidden="true"><x-ag.flag :code="$code" :width="18" /></span>
                        <span>{{ $label }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </details>
    <div class="install-theme" data-default-theme="system">
        <button
            type="button"
            class="install-control install-theme__toggle"
            data-installer-theme-toggle
            data-label-dark="{{ __('storefront.preferences.theme_to_dark') }}"
            data-label-light="{{ __('storefront.preferences.theme_to_light') }}"
            aria-label="{{ __('storefront.preferences.theme_to_dark') }}"
            title="{{ __('storefront.preferences.theme_to_dark') }}"
        >
            <span data-installer-theme-icon="light" aria-hidden="true"><x-ag.icon name="moon" :size="20" /></span>
            <span data-installer-theme-icon="dark" aria-hidden="true" hidden><x-ag.icon name="sun" :size="20" /></span>
        </button>
    </div>
</div>

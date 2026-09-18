<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('installer.title') }}</title>
    <link rel="icon" href="/vendor/agovena/logo.png" type="image/png">
    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
    @livewireStyles
</head>
<body class="install-app">
    <a class="admin-skip-link" href="#main">{{ __('admin.skip_to_content') }}</a>
    <div class="install-shell">
        <header class="install-shell__topbar" aria-label="{{ __('storefront.preferences.aria') }}">
            <div class="install-shell__preferences">
                @php
                    $installerLocales = config('agovena.locales', ['en' => 'English']);
                    $installerLocale = app()->getLocale();
                @endphp
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
        </header>
        <main id="main" class="install-shell__main">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>

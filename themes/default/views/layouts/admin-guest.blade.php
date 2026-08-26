<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('auth.sign_in') }} | {{ config('app.name', 'Agovena') }}</title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('agovena.theme');
                var defaultMode = @js(app(\App\Agovena\Theme\ThemeManager::class)->config()->string('appearance.default_color_mode', 'system'));
                var theme = stored === 'dark' || stored === 'light'
                    ? stored
                    : (defaultMode === 'dark' || defaultMode === 'light'
                        ? defaultMode
                        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    @php
        $adminTheme = app(\App\Agovena\Theme\ThemeManager::class)->themeFor(\App\Agovena\Theme\ThemeSurface::Admin);
        $adminAssets = array_values(array_filter([
            'resources/css/admin.css',
            $adminTheme->adminCssEntry,
            'resources/js/admin.js',
        ]));
    @endphp
    @vite($adminAssets)
    @livewireStyles
</head>
<body class="admin-app admin-app--guest">
    <a class="admin-skip-link" href="#main">{{ __('admin.skip_to_content') }}</a>
    <main id="main" class="admin-guest">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>

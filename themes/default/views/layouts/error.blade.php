@php
    $theme = $theme ?? app(\App\Agovena\Theme\ThemeManager::class)->active();
    $config = $themeConfig ?? null;
    $defaultMode = $config?->string('appearance.default_color_mode', 'system') ?? 'system';
    $accent = $config?->string('colors.accent', '#155EEF') ?? '#155EEF';
    $accentHover = $config?->string('colors.accent_hover', '#1249C7') ?? '#1249C7';
    $surface = $config?->string('colors.surface', '#ffffff') ?? '#ffffff';
    $background = $config?->string('colors.background', '#f4f6fa') ?? '#f4f6fa';
    $text = $config?->string('colors.text', '#0f172a') ?? '#0f172a';
    $textMuted = $config?->string('colors.text_muted', '#64748b') ?? '#64748b';
    $border = $config?->string('colors.border', '#e2e8f0') ?? '#e2e8f0';
    $borderStrong = $config?->string('colors.border_strong', '#cbd5e1') ?? '#cbd5e1';
    $darkAccent = $config?->string('colors.dark_accent', '#60a5fa') ?? '#60a5fa';
    $darkAccentHover = $config?->string('colors.dark_accent_hover', '#93c5fd') ?? '#93c5fd';
    $darkSurface = $config?->string('colors.dark_surface', '#111827') ?? '#111827';
    $darkBackground = $config?->string('colors.dark_background', '#0b1220') ?? '#0b1220';
    $darkText = $config?->string('colors.dark_text', '#f8fafc') ?? '#f8fafc';
    $darkTextMuted = $config?->string('colors.dark_text_muted', '#94a3b8') ?? '#94a3b8';
    $darkBorder = $config?->string('colors.dark_border', '#243044') ?? '#243044';
    $darkBorderStrong = $config?->string('colors.dark_border_strong', '#334155') ?? '#334155';
    $cssEntry = $theme->cssEntry;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('errors.500.title')) | {{ config('app.name', 'Agovena') }}</title>
    <link rel="icon" href="/{{ \App\Agovena\Theme\StorefrontBrand::BUNDLED_LOGO }}" type="image/png">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('agovena.theme');
                var defaultMode = @js($defaultMode);
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
    <style>
        :root {
            --theme-custom-light-accent: {{ $accent }};
            --theme-custom-light-accent-hover: {{ $accentHover }};
            --theme-custom-light-surface: {{ $surface }};
            --theme-custom-light-background: {{ $background }};
            --theme-custom-light-text: {{ $text }};
            --theme-custom-light-text-muted: {{ $textMuted }};
            --theme-custom-light-border: {{ $border }};
            --theme-custom-light-border-strong: {{ $borderStrong }};
            --theme-custom-dark-accent: {{ $darkAccent }};
            --theme-custom-dark-accent-hover: {{ $darkAccentHover }};
            --theme-custom-dark-surface: {{ $darkSurface }};
            --theme-custom-dark-background: {{ $darkBackground }};
            --theme-custom-dark-text: {{ $darkText }};
            --theme-custom-dark-text-muted: {{ $darkTextMuted }};
            --theme-custom-dark-border: {{ $darkBorder }};
            --theme-custom-dark-border-strong: {{ $darkBorderStrong }};
        }

        :root:not([data-theme="dark"]),
        [data-theme="light"] {
            --theme-color-surface: {{ $surface }};
            --theme-color-bg: {{ $background }};
            --theme-color-text: {{ $text }};
            --theme-color-text-muted: {{ $textMuted }};
            --theme-color-border: {{ $border }};
            --theme-color-border-strong: {{ $borderStrong }};
        }

        [data-theme="dark"] {
            --theme-color-accent: {{ $darkAccent }};
            --theme-color-accent-hover: {{ $darkAccentHover }};
            --theme-color-surface: {{ $darkSurface }};
            --theme-color-bg: {{ $darkBackground }};
            --theme-color-text: {{ $darkText }};
            --theme-color-ink: {{ $darkText }};
            --theme-color-text-muted: {{ $darkTextMuted }};
            --theme-color-border: {{ $darkBorder }};
            --theme-color-border-strong: {{ $darkBorderStrong }};
            --theme-focus: 0 0 0 3px color-mix(in srgb, {{ $darkAccent }} 40%, transparent);
        }
    </style>
    @vite([$cssEntry, 'resources/js/storefront.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="store {{ ($config?->bool('header.sticky', true) ?? true) ? 'store--sticky-header' : '' }}">
    <a class="store-skip" href="#main">{{ __('storefront.skip_to_content') }}</a>

    @include('theme::partials.header', ['themeConfig' => $config])

    <main id="main" class="store-main store-error-main" tabindex="-1">
        @yield('content')
    </main>

    @include('theme::partials.footer', ['themeConfig' => $config])
    @include('theme::partials.cookie-consent', ['showCookieBanner' => false])

    @livewireScripts
    @stack('scripts')
</body>
</html>

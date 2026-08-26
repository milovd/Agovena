<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('storefront.checkout.title') }} | {{ $siteName ?? __('storefront.shop') }}</title>
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @if (! empty($brandingFaviconUrl))
        <link rel="icon" href="{{ $brandingFaviconUrl }}">
    @endif
    @php
        $config = $themeConfig ?? null;
        if ($config === null && isset($theme)) {
            $config = app(\App\Agovena\Theme\ThemeManager::class)->config($theme);
        }
        $cssEntry = isset($theme) ? $theme->cssEntry : 'themes/default/resources/css/theme.css';
        $accent = $config?->string('colors.accent', '#155EEF') ?? '#155EEF';
        $accentHover = $config?->string('colors.accent_hover', '#1249C7') ?? '#1249C7';
        $surface = $config?->string('colors.surface', '#ffffff') ?? '#ffffff';
        $bg = $config?->string('colors.background', '#f4f6fa') ?? '#f4f6fa';
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
    @endphp
    <style>
        :root {
            --theme-custom-light-accent: {{ $accent }};
            --theme-custom-light-accent-hover: {{ $accentHover }};
            --theme-custom-light-surface: {{ $surface }};
            --theme-custom-light-background: {{ $bg }};
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
            --theme-color-accent: {{ $accent }};
            --theme-color-accent-hover: {{ $accentHover }};
            --theme-color-accent-soft: color-mix(in srgb, {{ $accent }} 12%, transparent);
            --theme-focus: 0 0 0 3px color-mix(in srgb, {{ $accent }} 35%, transparent);
        }

        /* Brand surface/bg/text are light-mode only: :root must not override dark tokens */
        :root:not([data-theme="dark"]),
        [data-theme="light"] {
            --theme-color-surface: {{ $surface }};
            --theme-color-bg: {{ $bg }};
            --theme-color-text: {{ $text }};
            --theme-color-text-muted: {{ $textMuted }};
            --theme-color-border: {{ $border }};
            --theme-color-border-strong: {{ $borderStrong }};
        }

        [data-theme="dark"] {
            --theme-color-accent: {{ $darkAccent }};
            --theme-color-accent-hover: {{ $darkAccentHover }};
            --theme-color-accent-soft: color-mix(in srgb, {{ $darkAccent }} 16%, transparent);
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
    @vite([$cssEntry])
    @livewireStyles
</head>
<body class="store store--checkout store--sticky-header">
    <a class="store-skip" href="#main">{{ __('storefront.skip_to_content') }}</a>
    @include('theme::partials.header', ['themeConfig' => $config])
    <main id="main" class="store-main store-main--checkout" tabindex="-1">
        @if (session('status'))
            <p class="store-flash" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="store-flash store-flash--error" role="alert">{{ session('error') }}</p>
        @endif
        {{ $slot }}
    </main>
    @include('theme::partials.footer', ['themeConfig' => $config])
    @livewireScripts
</body>
</html>

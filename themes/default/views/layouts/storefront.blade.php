<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Shop' }} — {{ $siteName ?? 'Shop' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    @if (! empty($brandingFaviconPath))
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingFaviconPath) }}">
    @endif
    @php
        $config = $themeConfig ?? null;
        if ($config === null && isset($theme)) {
            $config = app(\App\Agovena\Theme\ThemeManager::class)->config($theme);
        }
        $cssEntry = isset($theme) ? $theme->cssEntry : 'themes/default/resources/css/theme.css';
        $accent = $config?->string('colors.accent', '#1f4e46') ?? '#1f4e46';
        $accentHover = $config?->string('colors.accent_hover', '#2a6b60') ?? '#2a6b60';
        $surface = $config?->string('colors.surface', '#ffffff') ?? '#ffffff';
        $bg = $config?->string('colors.background', '#f4f1ec') ?? '#f4f1ec';
        $text = $config?->string('colors.text', '#1a1814') ?? '#1a1814';
        $perRow = $config?->string('catalog.products_per_row', '3') ?? '3';
        $ratio = $config?->string('catalog.image_ratio', '4/3') ?? '4/3';
    @endphp
    <style>
        :root {
            --theme-color-accent: {{ $accent }};
            --theme-color-accent-hover: {{ $accentHover }};
            --theme-color-surface: {{ $surface }};
            --theme-color-bg: {{ $bg }};
            --theme-color-text: {{ $text }};
            --theme-products-per-row: {{ $perRow }};
            --theme-card-ratio: {{ $ratio }};
        }
    </style>
    @vite([$cssEntry])
    @livewireStyles
</head>
<body
    class="store {{ ($config?->bool('header.sticky', true) ?? true) ? 'store--sticky-header' : '' }}"
    x-data="{ navOpen: false }"
    @keydown.escape.window="navOpen = false"
>
    <a class="store-skip" href="#main">Skip to content</a>

    @include('theme::partials.header', ['themeConfig' => $config])

    <main id="main" class="store-main" tabindex="-1">
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

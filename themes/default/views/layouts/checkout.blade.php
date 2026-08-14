<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('storefront.checkout.title') }} | {{ $siteName ?? __('storefront.shop') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @if (! empty($brandingFaviconPath))
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingFaviconPath) }}">
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
    @endphp
    <style>
        :root {
            --theme-color-accent: {{ $accent }};
            --theme-color-accent-hover: {{ $accentHover }};
            --theme-color-accent-soft: color-mix(in srgb, {{ $accent }} 12%, transparent);
            --theme-color-surface: {{ $surface }};
            --theme-color-bg: {{ $bg }};
            --theme-color-text: {{ $text }};
            --theme-focus: 0 0 0 3px color-mix(in srgb, {{ $accent }} 35%, transparent);
        }
    </style>
    @vite([$cssEntry])
    @livewireStyles
</head>
<body class="store store--checkout">
    <a class="store-skip" href="#main">{{ __('storefront.skip_to_content') }}</a>
    @include('theme::partials.checkout-header')
    <main id="main" class="store-main store-main--checkout" tabindex="-1">
        @if (session('status'))
            <p class="store-flash" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="store-flash store-flash--error" role="alert">{{ session('error') }}</p>
        @endif
        {{ $slot }}
    </main>
    <footer class="store-checkout-footer">
        <p>
            <a href="{{ route('storefront.cart') }}">{{ __('storefront.checkout.back_to_cart') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('storefront.home') }}">{{ __('storefront.cart.continue_shopping') }}</a>
        </p>
    </footer>
    @livewireScripts
</body>
</html>

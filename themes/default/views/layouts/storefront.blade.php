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
    @vite(['themes/default/resources/css/theme.css'])
    @livewireStyles
</head>
<body
    class="store"
    x-data="{ navOpen: false, searchOpen: false }"
    @keydown.escape.window="navOpen = false; searchOpen = false"
>
    <a class="store-skip" href="#main">Skip to content</a>

    @include('theme::partials.header')

    <main id="main" class="store-main" tabindex="-1">
        @if (session('status'))
            <p class="store-flash" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="store-flash store-flash--error" role="alert">{{ session('error') }}</p>
        @endif
        {{ $slot }}
    </main>

    @include('theme::partials.footer')

    @livewireScripts
</body>
</html>

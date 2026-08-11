<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Shop' }} — {{ config('app.name', 'Agovena') }}</title>
    @vite(['themes/default/resources/css/theme.css'])
    @livewireStyles
</head>
<body class="store">
    <a class="store-skip" href="#main">Skip to content</a>
    <header class="store-header">
        <a class="store-brand" href="{{ route('storefront.home') }}">{{ config('app.name', 'Shop') }}</a>
        <nav class="store-nav" aria-label="Store">
            <a class="store-nav__link" href="{{ route('storefront.home') }}">Catalog</a>
            <a class="store-nav__link" href="{{ route('storefront.cart') }}">Cart</a>
        </nav>
    </header>
    <main id="main" class="store-main">
        @if (session('status'))
            <p class="store-flash" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="store-flash store-flash--error" role="alert">{{ session('error') }}</p>
        @endif
        {{ $slot }}
    </main>
    <footer class="store-footer">
        <p>Powered by a default Theme. Customer order history comes in a later Core step.</p>
    </footer>
    @livewireScripts
</body>
</html>

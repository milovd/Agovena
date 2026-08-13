<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Agovena'))</title>
    <link rel="icon" href="{{ asset('vendor/agovena/logo.png') }}" type="image/png">
    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
</head>
<body class="install-app">
    <a class="admin-skip-link" href="#main">{{ __('admin.skip_to_content') }}</a>
    <div class="install-shell">
        <header class="install-shell__brand">
            <x-ag.logo class="install-shell__logo" :alt="__('installer.brand_alt')" />
            <div>
                <p class="install-shell__product">Agovena</p>
                <p class="install-shell__tagline">@yield('tagline', __('installer.tagline'))</p>
            </div>
        </header>
        <main id="main" class="install-shell__main">
            @yield('content')
        </main>
        <footer class="install-shell__footer">
            <p>@yield('footer', __('installer.footer'))</p>
        </footer>
    </div>
</body>
</html>

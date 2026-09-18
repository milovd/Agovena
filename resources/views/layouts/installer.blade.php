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
        <main id="main" class="install-shell__main">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>

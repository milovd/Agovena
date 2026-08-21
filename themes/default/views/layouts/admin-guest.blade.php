<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('auth.sign_in') }} | {{ config('app.name', 'Agovena') }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
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

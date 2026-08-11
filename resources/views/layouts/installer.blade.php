<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Install Agovena' }}</title>
    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
</head>
<body class="admin-app">
    <main id="main" class="admin-content" style="max-width: 40rem; margin: 0 auto;">
        {{ $slot }}
    </main>
</body>
</html>

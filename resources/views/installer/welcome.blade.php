<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('installer.title') }}</title>
    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
</head>
<body class="admin-app">
    <main class="admin-content" style="max-width: 40rem; margin: 0 auto;">
        <section class="admin-panel" aria-labelledby="install-heading">
            <h1 id="install-heading" class="admin-panel__title">{{ __('installer.heading') }}</h1>
            <p class="ag-field__hint">
                {!! __('installer.lede', ['command' => '<code>php artisan agovena:doctor</code>']) !!}
            </p>
            <p>
                <a class="ag-btn ag-btn--primary" href="{{ url('/admin') }}">{{ __('installer.open_admin') }}</a>
            </p>
        </section>
    </main>
</body>
</html>

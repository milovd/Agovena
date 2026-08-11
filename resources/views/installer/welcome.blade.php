<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Agovena</title>
    @vite(['resources/css/installer.css', 'resources/js/installer.js'])
</head>
<body class="admin-app">
    <main class="admin-content" style="max-width: 40rem; margin: 0 auto;">
        <section class="admin-panel" aria-labelledby="install-heading">
            <h1 id="install-heading" class="admin-panel__title">Agovena installer</h1>
            <p class="ag-field__hint">
                Web installer wizard will live here. For now, use Composer setup and
                <code>php artisan agovena:doctor</code>.
            </p>
            <p>
                <a class="ag-btn ag-btn--primary" href="{{ url('/admin') }}">Open Admin</a>
            </p>
        </section>
    </main>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Agovena Admin' }} — {{ config('app.name', 'Agovena') }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @livewireStyles
</head>
<body class="admin-app">
    <a class="admin-skip-link" href="#main">Skip to content</a>

    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Primary">
            <div class="admin-sidebar__brand">
                <span class="admin-sidebar__logo" aria-hidden="true"></span>
                <span class="admin-sidebar__title">{{ config('app.name', 'Agovena') }}</span>
            </div>
            <nav class="admin-nav">
                @php
                    $staff = auth('staff')->user();
                    $nav = collect($navigation ?? [])->filter(function ($item) use ($staff) {
                        return $item->permission === null
                            || ($staff !== null && $staff->can($item->permission));
                    });
                    $groups = $nav->groupBy(fn ($item) => $item->group);
                @endphp
                @foreach ($groups as $group => $items)
                    <p class="admin-nav__group">{{ $group }}</p>
                    @foreach ($items as $item)
                        <a
                            class="admin-nav__link @if(request()->is(ltrim($item->href ?? '', '/'))) admin-nav__link--active @endif"
                            href="{{ $item->href ?? '#' }}"
                            @if(request()->is(ltrim($item->href ?? '', '/'))) aria-current="page" @endif
                        >
                            {{ $item->label }}
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <h1 class="admin-topbar__title">{{ $title ?? 'Admin' }}</h1>
                <div class="admin-topbar__actions">
                    <livewire:admin.auth.logout />
                </div>
            </header>

            <main id="main" class="admin-content">
                @if (session('status'))
                    <div class="ag-alert ag-alert--success" role="status">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>

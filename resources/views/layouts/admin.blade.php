<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('admin.fallback_title') }} | {{ $siteName ?? config('app.name', 'Agovena') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @if (! empty($brandingFaviconPath))
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingFaviconPath) }}">
    @endif
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @livewireStyles
</head>
<body class="admin-app" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
    <a class="admin-skip-link" href="#main">{{ __('admin.skip_to_content') }}</a>

    <div
        class="admin-shell"
        :class="{ 'admin-shell--nav-open': navOpen }"
        wire:loading.class="admin-shell--loading"
    >
        <div class="admin-shell__backdrop" x-show="navOpen" x-cloak @click="navOpen = false"></div>

        <aside class="admin-sidebar" id="admin-sidebar" aria-label="{{ __('admin.sidebar_aria') }}">
            <div class="admin-sidebar__brand">
                @if (! empty($brandingLogoPath))
                    <img class="admin-sidebar__logo-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingLogoPath) }}" alt="">
                @else
                    <span class="admin-sidebar__logo" aria-hidden="true"></span>
                @endif
                <span class="admin-sidebar__title">{{ $siteName ?? config('app.name', 'Agovena') }}</span>
            </div>
            <nav class="admin-nav" aria-label="{{ __('admin.nav_aria') }}">
                @php
                    use App\Agovena\Admin\AdminNavigation;
                    $staff = auth()->user();
                    $nav = collect($navigation ?? [])->filter(function ($item) use ($staff) {
                        $authorized = $item->permission === null
                            || ($staff !== null && $staff->can($item->permission));

                        return $authorized && is_string($item->href) && $item->href !== '';
                    });
                    $groups = $nav->groupBy(fn ($item) => $item->group);
                @endphp
                @foreach ($groups as $group => $items)
                    <div class="admin-nav__section">
                        <p class="admin-nav__group" id="nav-group-{{ \Illuminate\Support\Str::slug($group) }}">{{ __($group) }}</p>
                        <ul class="admin-nav__list" role="list" aria-labelledby="nav-group-{{ \Illuminate\Support\Str::slug($group) }}">
                            @foreach ($items as $item)
                                @php $active = AdminNavigation::isActive($item->href); @endphp
                                <li>
                                    <a
                                        class="admin-nav__link @if($active) admin-nav__link--active @endif"
                                        href="{{ $item->href }}"
                                        @if($active) aria-current="page" @endif
                                    >
                                        @if ($item->icon)
                                            <x-ag.icon :name="$item->icon" class="admin-nav__icon" :size="18" />
                                        @endif
                                        <span class="admin-nav__label">{{ __($item->label) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar__start">
                    <button
                        type="button"
                        class="admin-topbar__menu ag-btn ag-btn--ghost"
                        @click="navOpen = !navOpen"
                        :aria-expanded="navOpen.toString()"
                        aria-controls="admin-sidebar"
                    >
                        <x-ag.icon name="menu" :size="18" />
                        <span class="visually-hidden">{{ __('admin.menu') }}</span>
                    </button>
                    <h1 class="admin-topbar__title">{{ $title ?? __('admin.fallback_title') }}</h1>
                </div>
                <div class="admin-topbar__actions">
                    <div
                        class="admin-topbar__loading"
                        wire:loading
                        wire:target="save,create,edit,setAsBase,useCurrentLogoAsFavicon,placeOrder,logout"
                        aria-live="polite"
                    >
                        <x-ag.icon name="loader" class="ag-icon--spin" :size="18" />
                        <span class="visually-hidden">{{ __('common.loading') }}</span>
                    </div>
                    <div
                        class="ag-dropdown"
                        x-data="{ open: false }"
                        @keydown.escape.window="open = false"
                        @click.outside="open = false"
                    >
                        <button
                            type="button"
                            class="ag-btn ag-btn--ghost ag-dropdown__trigger"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-haspopup="menu"
                        >
                            {{ auth()->user()?->name ?? __('admin.account') }}
                        </button>
                        <div
                            class="ag-dropdown__menu"
                            x-show="open"
                            x-cloak
                            role="menu"
                            @keydown.escape.stop="open = false"
                        >
                            <p class="ag-dropdown__meta">{{ auth()->user()?->email }}</p>
                            <livewire:admin.auth.logout />
                        </div>
                    </div>
                </div>
            </header>

            <main id="main" class="admin-content" tabindex="-1">
                @if (session('status'))
                    <div class="ag-alert ag-alert--success" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</div>
                @endif
                @if (($schemaPendingCount ?? 0) > 0 && ! request()->routeIs('admin.updates') && auth()->user()?->can('settings.view'))
                    <div class="ag-alert ag-alert--warning" role="status">
                        <div class="ag-alert__body">
                            <p class="ag-alert__title">{{ __('admin.updates.banner_title') }}</p>
                            <p class="ag-alert__text">{{ __('admin.updates.banner_text') }}</p>
                        </div>
                        <a class="ag-btn ag-btn--secondary ag-btn--sm" href="{{ route('admin.updates') }}">{{ __('admin.updates.banner_action') }}</a>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>

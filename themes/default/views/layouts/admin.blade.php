<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('admin.fallback_title') }} | {{ $siteName ?? config('app.name', 'Agovena') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @if (! empty($brandingFaviconUrl))
        <link rel="icon" href="{{ $brandingFaviconUrl }}">
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
                <img class="admin-sidebar__logo-img" src="/{{ \App\Agovena\Theme\StorefrontBrand::BUNDLED_LOGO }}" alt="{{ __('admin.product_name') }}">
                <span class="admin-sidebar__brand-text">
                    <span class="admin-sidebar__title">{{ __('admin.product_name') }}</span>
                    @if (! empty($siteName) && strcasecmp($siteName, __('admin.product_name')) !== 0)
                        <span class="admin-sidebar__subtitle">{{ $siteName }}</span>
                    @endif
                </span>
            </div>
            <nav class="admin-nav" aria-label="{{ __('admin.nav_aria') }}">
                @include('partials.admin-nav')
            </nav>
            <div class="admin-sidebar__footer">
                <p class="admin-sidebar__footer-meta">{{ __('admin.product_name') }}</p>
            </div>
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
                    <a
                        class="ag-btn ag-btn--secondary ag-btn--sm admin-topbar__storefront"
                        href="{{ route('storefront.home') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <x-ag.icon name="store" :size="16" />
                        <span>{{ __('admin.view_storefront') }}</span>
                    </a>
                    <div
                        class="ag-dropdown admin-account-dropdown"
                        x-data="{ open: false }"
                        @keydown.escape.window="open = false"
                        @click.outside="open = false"
                    >
                        @php
                            $accountName = auth()->user()?->name ?? __('admin.account');
                            $accountInitials = collect(preg_split('/\s+/', trim($accountName)) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part): string => mb_substr($part, 0, 1))
                                ->implode('');
                            if ($accountInitials === '') {
                                $accountInitials = 'A';
                            }
                            $accountRole = auth()->user()?->roles->pluck('name')->first();
                        @endphp
                        <button
                            type="button"
                            class="ag-btn ag-btn--ghost ag-dropdown__trigger admin-account-trigger"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-haspopup="menu"
                        >
                            <span class="admin-account-trigger__avatar" aria-hidden="true">{{ $accountInitials }}</span>
                            <span class="admin-account-trigger__name">{{ $accountName }}</span>
                            <x-ag.icon name="chevron-down" :size="16" />
                        </button>
                        <div
                            class="ag-dropdown__menu admin-account-menu"
                            x-show="open"
                            x-cloak
                            role="menu"
                            @keydown.escape.stop="open = false"
                        >
                            <div class="admin-account-menu__identity">
                                <span class="admin-account-menu__avatar" aria-hidden="true">{{ $accountInitials }}</span>
                                <span class="admin-account-menu__details">
                                    <strong class="admin-account-menu__name">{{ $accountName }}</strong>
                                    <span class="admin-account-menu__email">{{ auth()->user()?->email }}</span>
                                    @if ($accountRole)
                                        <span class="admin-account-menu__role">{{ $accountRole }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="ag-dropdown__divider" role="separator"></div>
                            @can('settings.view')
                                <a class="ag-dropdown__item" role="menuitem" href="{{ route('admin.settings.index') }}">
                                    <x-ag.icon name="settings" :size="16" />
                                    {{ __('admin.nav.settings') }}
                                </a>
                            @endcan
                            <a class="ag-dropdown__item" role="menuitem" href="{{ route('admin.security.two-factor') }}">
                                <x-ag.icon name="shield" :size="16" />
                                {{ __('admin.nav.security') }}
                            </a>
                            <div class="ag-dropdown__divider" role="separator"></div>
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

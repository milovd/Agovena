<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('admin.fallback_title') }} | {{ $siteName ?? config('app.name', 'Agovena') }}</title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('agovena.theme');
                var defaultMode = @js(app(\App\Agovena\Theme\ThemeManager::class)->config()->string('appearance.default_color_mode', 'system'));
                var theme = stored === 'dark' || stored === 'light'
                    ? stored
                    : (defaultMode === 'dark' || defaultMode === 'light'
                        ? defaultMode
                        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
            try {
                if (localStorage.getItem('agovena.admin.sidebar') === 'collapsed') {
                    document.documentElement.setAttribute('data-sidebar', 'collapsed');
                }
            } catch (e) {}
        })();
    </script>

    @if (! empty($brandingFaviconUrl))
        <link rel="icon" href="{{ $brandingFaviconUrl }}">
    @endif
    @php
        $adminTheme = app(\App\Agovena\Theme\ThemeManager::class)->themeFor(\App\Agovena\Theme\ThemeSurface::Admin);
        $adminAssets = array_values(array_filter([
            'resources/css/admin.css',
            $adminTheme->adminCssEntry,
            'resources/js/admin.js',
        ]));
    @endphp
    @vite($adminAssets)
    @livewireStyles
</head>
<body class="admin-app" x-data="agAdminShell" @keydown.escape.window="closeNav()" @keydown.window="onShortcut($event)" @agovena-sidebar-expand.window="expandSidebar()">
    <a class="admin-skip-link" href="#main">{{ __('admin.skip_to_content') }}</a>

    <div
        class="admin-shell"
        :class="{ 'admin-shell--nav-open': navOpen }"
        wire:loading.class="admin-shell--loading"
    >
        <div class="admin-shell__backdrop" x-show="navOpen" x-cloak @click="closeNav()"></div>

        <aside
            class="admin-sidebar"
            id="admin-sidebar"
            data-collapsible="icon"
            aria-label="{{ __('admin.sidebar_aria') }}"
            x-ref="sidebar"
            :role="navOpen && isMobile ? 'dialog' : null"
            :aria-modal="navOpen && isMobile ? 'true' : null"
            :inert="isMobile && !navOpen"
            @keydown.tab="trapNavFocus($event)"
            @mouseover="showTooltip($event)"
            @mouseout="hideTooltip()"
            @focusin="showTooltip($event)"
            @focusout="hideTooltip()"
        >
            <div class="admin-sidebar__header">
                <a class="admin-sidebar__brand" href="{{ route('admin.dashboard') }}" data-tooltip="{{ __('admin.product_name') }}">
                    <img class="admin-sidebar__logo-img" src="/{{ \App\Agovena\Theme\StorefrontBrand::BUNDLED_LOGO }}" alt="">
                    <span class="admin-sidebar__brand-text">
                        <span class="admin-sidebar__title">{{ __('admin.product_name') }}</span>
                        @if (! empty($siteName) && strcasecmp($siteName, __('admin.product_name')) !== 0)
                            <span class="admin-sidebar__subtitle">{{ $siteName }}</span>
                        @endif
                    </span>
                </a>
                <button
                    type="button"
                    class="admin-sidebar__toggle"
                    @click="toggleSidebar()"
                    aria-controls="admin-sidebar"
                    :aria-expanded="(!collapsed).toString()"
                    aria-label="{{ __('common.toggle_sidebar') }}"
                    data-tooltip="{{ __('common.toggle_sidebar') }}"
                >
                    <x-ag.icon name="panel-left" :size="16" />
                </button>
                <button
                    type="button"
                    class="admin-sidebar__close js-admin-drawer-close"
                    @click="closeNav()"
                    aria-controls="admin-sidebar"
                >
                    <x-ag.icon name="x" :size="20" />
                    <span class="visually-hidden">{{ __('common.close') }}</span>
                </button>
            </div>
            @php
                $supportLinks = config('agovena.admin.support_links', []);
                $footerLinks = [
                    ['key' => 'sponsor', 'icon' => 'heart'],
                    ['key' => 'github', 'icon' => 'star'],
                    ['key' => 'documentation', 'icon' => 'book-open'],
                ];
            @endphp
            <div class="admin-sidebar__scroll">
                <nav class="admin-nav" aria-label="{{ __('admin.nav_aria') }}">
                    @include('partials.admin.nav')
                    <div class="admin-sidebar__footer">
                        <ul class="admin-nav__list" role="list">
                            @foreach ($footerLinks as $footerLink)
                                @php $footerHref = $supportLinks[$footerLink['key']] ?? null; @endphp
                                @if (is_string($footerHref) && $footerHref !== '')
                                    <li class="admin-nav__item">
                                        <a
                                            class="admin-sidebar__footer-link admin-sidebar__footer-link--{{ $footerLink['key'] }}"
                                            href="{{ $footerHref }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            data-tooltip="{{ __('admin.sidebar_links.'.$footerLink['key']) }}"
                                        >
                                            <x-ag.icon name="{{ $footerLink['icon'] }}" class="admin-sidebar__footer-icon" :size="16" />
                                            <span class="admin-sidebar__footer-label">{{ __('admin.sidebar_links.'.$footerLink['key']) }}</span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </nav>
            </div>
            <div class="admin-sidebar__tooltip" x-ref="navTooltip" aria-hidden="true" hidden></div>
        </aside>

        <div class="admin-main" :inert="isMobile && navOpen">
            <header class="admin-topbar">
                <div class="admin-topbar__start">
                    <nav class="admin-topbar__breadcrumb" aria-label="{{ __('admin.breadcrumb_aria') }}">
                        <a href="{{ route('admin.dashboard') }}">{{ __('admin.fallback_title') }}</a>
                        <x-ag.icon name="chevron-right" :size="14" />
                        <span aria-current="page">{{ $title ?? __('admin.fallback_title') }}</span>
                    </nav>
                </div>
                <div class="admin-topbar__actions">
                    <div
                        class="admin-topbar__loading"
                        wire:loading
                        wire:target="save,create,edit,setAsBase,useCurrentLogoAsFavicon,placeOrder,logout,createUser,saveUser,cancelUser,saveRoles"
                        aria-live="polite"
                    >
                        <x-ag.icon name="loader" class="ag-icon--spin" :size="20" />
                        <span class="visually-hidden">{{ __('common.loading') }}</span>
                    </div>
                    <button
                        type="button"
                        class="admin-theme-toggle"
                        @click="toggleTheme()"
                        :aria-label="theme === 'dark' ? @js(__('admin.theme_to_light')) : @js(__('admin.theme_to_dark'))"
                        :title="theme === 'dark' ? @js(__('admin.theme_to_light')) : @js(__('admin.theme_to_dark'))"
                    >
                        <span class="admin-theme-toggle__icon" x-show="theme !== 'dark'" x-cloak aria-hidden="true">
                            <x-ag.icon name="moon" :size="20" />
                        </span>
                        <span class="admin-theme-toggle__icon" x-show="theme === 'dark'" x-cloak aria-hidden="true">
                            <x-ag.icon name="sun" :size="20" />
                        </span>
                    </button>
                    <div
                        class="ag-dropdown admin-account-dropdown"
                        x-data="agAdminAccount"
                        @keydown.escape.window="close()"
                        @click.outside="close()"
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
                            class="admin-account-trigger"
                            @click="toggle()"
                            :aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            aria-label="{{ __('admin.account_menu') }}"
                        >
                            <x-ag.icon name="user" :size="22" />
                            <span class="visually-hidden">{{ $accountName }}</span>
                        </button>
                        <div
                            class="ag-dropdown__menu admin-account-menu"
                            x-show="open"
                            x-cloak
                            role="menu"
                            @keydown.escape.stop="close()"
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
                            <a class="ag-dropdown__item" role="menuitem" href="{{ route('customer.security') }}">
                                <x-ag.icon name="shield" :size="16" />
                                {{ __('admin.nav.security') }}
                            </a>
                            <div class="ag-dropdown__divider" role="separator"></div>
                            <a class="ag-dropdown__item" role="menuitem" href="{{ route('storefront.home') }}">
                                <x-ag.icon name="store" :size="16" />
                                {{ __('admin.exit_admin') }}
                            </a>
                            <div class="ag-dropdown__divider" role="separator"></div>
                            <livewire:admin.auth.logout />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="admin-topbar__menu ag-btn ag-btn--ghost"
                        @click="toggleNav()"
                        :aria-expanded="navOpen.toString()"
                        aria-controls="admin-sidebar"
                    >
                        <x-ag.icon name="menu" :size="20" />
                        <span class="visually-hidden">{{ __('admin.menu') }}</span>
                    </button>
                </div>
            </header>

            <main id="main" class="admin-content" tabindex="-1">
                {{-- Pages render feedback from their own actions; repeat it here only when the page did not. --}}
                @php($pageHtml = (string) $slot)
                @if (session('status') && ! str_contains($pageHtml, '>'.e(session('status')).'</'))
                    <div class="ag-alert ag-alert--success" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error') && ! str_contains($pageHtml, '>'.e(session('error')).'</'))
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

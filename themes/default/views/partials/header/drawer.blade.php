{{-- Mobile navigation drawer (Alpine scope: storefrontHeader). --}}
<div
    id="store-mobile-nav"
    class="store-drawer"
    :style="'top: ' + drawerTop + 'px'"
    x-show="navOpen"
    x-bind:hidden="!navOpen"
    x-cloak
    x-transition:enter="store-drawer--enter"
    x-transition:enter-start="store-drawer--enter-start"
    x-transition:enter-end="store-drawer--enter-end"
    x-transition:leave="store-drawer--leave"
    x-transition:leave-start="store-drawer--leave-start"
    x-transition:leave-end="store-drawer--leave-end"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('storefront.menu') }}"
>
    <div class="store-drawer__backdrop" @click="closeDrawer()"></div>
    <div
        class="store-drawer__panel"
        x-transition:enter="store-drawer__panel--enter"
        x-transition:enter-start="store-drawer__panel--enter-start"
        x-transition:enter-end="store-drawer__panel--enter-end"
        x-transition:leave="store-drawer__panel--leave"
        x-transition:leave-start="store-drawer__panel--leave-start"
        x-transition:leave-end="store-drawer__panel--leave-end"
    >
        <div class="store-drawer__head">
            <p class="store-drawer__title">{{ __('storefront.menu') }}</p>
        </div>
        <div class="store-drawer__preferences">
            <p class="store-drawer__section-label">{{ __('storefront.preferences.aria') }}</p>
            @include('theme::partials.header-preferences', ['isMobile' => true])
        </div>
        <nav class="store-drawer__nav" aria-label="{{ __('storefront.mobile_nav') }}">
            @include('theme::partials.header.drawer-categories')
            @foreach ($navItems as $item)
                <a class="store-nav__link store-drawer__primary-link" href="{{ $item['url'] }}" @click="closeDrawerOnNavigate">{{ $item['label'] }}</a>
            @endforeach
            @include('theme::partials.header.drawer-account')
        </nav>
    </div>
</div>

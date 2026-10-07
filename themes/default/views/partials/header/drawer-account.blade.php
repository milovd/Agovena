{{-- Account links (signed in) or sign in / register buttons inside the mobile drawer. --}}
@if ($showAccount)
    @auth
        @php
            $drawerAccountUser = auth()->user();
            $drawerCanAdmin = $drawerAccountUser instanceof \App\Models\User && $drawerAccountUser->canAccessAdmin();
            $drawerAccountName = $drawerAccountUser instanceof \App\Models\User && filled($drawerAccountUser->name)
                ? $drawerAccountUser->name
                : __('storefront.nav.account');
        @endphp
        <div class="store-drawer__account" :class="{ 'is-open': mobileAccountOpen }">
            <button
                type="button"
                class="store-nav__link store-nav__link--btn store-drawer__primary-link store-drawer__account-toggle"
                @click="toggleMobileAccount()"
                :aria-expanded="mobileAccountOpen.toString()"
                aria-controls="store-mobile-account"
                aria-haspopup="true"
            >
                <span class="store-drawer__account-identity">
                    <span class="store-drawer__account-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'user', 'size' => 18])</span>
                    <span class="store-drawer__account-name">{{ $drawerAccountName }}</span>
                </span>
                <svg class="store-drawer__account-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div
                id="store-mobile-account"
                class="store-drawer__account-panel"
                x-show="mobileAccountOpen"
                x-bind:hidden="!mobileAccountOpen"
                x-cloak
                x-transition:enter="store-drawer__account-panel--enter"
                x-transition:enter-start="store-drawer__account-panel--enter-start"
                x-transition:enter-end="store-drawer__account-panel--enter-end"
                x-transition:leave="store-drawer__account-panel--leave"
                x-transition:leave-start="store-drawer__account-panel--leave-start"
                x-transition:leave-end="store-drawer__account-panel--leave-end"
                role="region"
                aria-label="{{ __('storefront.nav.account') }}"
            >
                <a class="store-drawer__link" href="{{ route('customer.account') }}" @click="closeDrawerOnNavigate">
                    <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'layout-dashboard', 'size' => 18])</span>
                    <span class="store-drawer__link-text">{{ __('storefront.nav.dashboard') }}</span>
                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                </a>
                <a class="store-drawer__link" href="{{ route('customer.profile') }}" @click="closeDrawerOnNavigate">
                    <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'user', 'size' => 18])</span>
                    <span class="store-drawer__link-text">{{ __('storefront.nav.account') }}</span>
                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                </a>
                <a
                    class="store-drawer__link store-drawer__link--notifications"
                    href="{{ route('customer.notifications') }}"
                    @click="closeDrawerOnNavigate"
                    aria-label="{{ __('customer.notifications.title') }}{{ ($notificationUnreadCount ?? 0) > 0 ? ', '.trans_choice('customer.notifications.unread_count', $notificationUnreadCount, ['count' => $notificationUnreadCount]) : '' }}"
                >
                    <span class="store-drawer__link-icon store-drawer__notification-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'bell', 'size' => 18])</span>
                    <span class="store-drawer__link-text">{{ __('customer.notifications.title') }}</span>
                    @if (($notificationUnreadCount ?? 0) > 0)
                        <span class="store-drawer__count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                    @endif
                    <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                </a>
                @if ($drawerCanAdmin)
                    <a class="store-drawer__link" href="{{ route('admin.dashboard') }}" @click="closeDrawerOnNavigate">
                        <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'settings', 'size' => 18])</span>
                        <span class="store-drawer__link-text">{{ __('storefront.nav.admin') }}</span>
                        <span class="store-drawer__link-arrow" aria-hidden="true">@include('theme::partials.icon', ['name' => 'chevron-right', 'size' => 16])</span>
                    </a>
                @endif
                <form method="POST" action="{{ route('customer.logout') }}" class="store-drawer__logout-form" @submit="navOpen = false">
                    @csrf
                    <button type="submit" class="store-drawer__link store-drawer__link--danger">
                        <span class="store-drawer__link-icon" aria-hidden="true">@include('theme::partials.icon', ['name' => 'log-out', 'size' => 18])</span>
                        <span class="store-drawer__link-text">{{ __('storefront.nav.logout') }}</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="store-drawer__auth">
            <a class="store-btn store-btn--ghost store-drawer__auth-login" href="{{ route('login') }}" @click="closeDrawerOnNavigate">{{ __('storefront.nav.login') }}</a>
            <a class="store-btn store-btn--primary store-drawer__auth-register" href="{{ route('register') }}" @click="closeDrawerOnNavigate">{{ __('storefront.nav.register') }}</a>
        </div>
    @endauth
@endif

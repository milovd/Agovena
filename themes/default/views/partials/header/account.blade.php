{{-- Header account menu (signed in) or sign in / register links. --}}
@if ($showAccount)
    @auth
        @php
            $accountUser = auth()->user();
            $canOpenAdmin = $accountUser instanceof \App\Models\User && $accountUser->canAccessAdmin();
        @endphp
        <div
            class="store-header__account"
            x-data="storefrontDisclosure"
            @keydown.escape.window="closeAndFocus()"
            @click.outside="close()"
        >
            <button
                type="button"
                x-ref="trigger"
                class="store-header__utility store-header__account-trigger"
                id="store-account-menu-button"
                @click="toggleAndFocusMenu()"
                @keydown.enter.prevent="toggleAndFocusMenu()"
                @keydown.space.prevent="toggleAndFocusMenu()"
                :aria-expanded="open.toString()"
                :class="{ 'is-open': open }"
                aria-haspopup="menu"
                aria-controls="store-account-menu"
                aria-label="{{ __('storefront.nav.account_menu') }}{{ $notificationUnreadCount > 0 ? ', '.trans_choice('customer.notifications.unread_count', $notificationUnreadCount, ['count' => $notificationUnreadCount]) : '' }}"
            >
                @include('theme::partials.icon', ['name' => 'user', 'size' => 22, 'class' => 'store-icon store-header__account-icon'])
                <span class="visually-hidden">{{ __('storefront.nav.account_menu') }}</span>
                @if ($notificationUnreadCount > 0)
                    <span class="store-header__account-count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                @endif
            </button>
            <div
                id="store-account-menu"
                x-ref="menu"
                class="store-header__account-menu store-account-menu"
                style="left: unset; right: 0;"
                x-show="open"
                x-cloak
                x-transition:enter="store-account-menu-enter"
                x-transition:enter-start="store-account-menu-enter-start"
                x-transition:enter-end="store-account-menu-enter-end"
                x-transition:leave="store-account-menu-leave"
                x-transition:leave-start="store-account-menu-leave-start"
                x-transition:leave-end="store-account-menu-leave-end"
                role="menu"
                aria-labelledby="store-account-menu-button"
                @keydown.escape.stop="closeAndFocus()"
            >
                @include('theme::partials.header.account-menu', [
                    'accountUser' => $accountUser,
                    'canOpenAdmin' => $canOpenAdmin,
                    'notificationUnreadCount' => $notificationUnreadCount,
                ])
            </div>
        </div>
    @else
        <div class="store-header__auth">
            <a class="store-header__auth-link" href="{{ route('login') }}">{{ __('storefront.nav.login') }}</a>
            <a class="store-header__auth-register" href="{{ route('register') }}">{{ __('storefront.nav.register') }}</a>
        </div>
    @endauth
@endif

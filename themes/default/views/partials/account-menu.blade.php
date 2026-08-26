@php
    /** @var \App\Models\User|null $accountUser */
    $accountUser = $accountUser ?? auth()->user();
    $canOpenAdmin = $canOpenAdmin ?? ($accountUser instanceof \App\Models\User && $accountUser->canAccessAdmin());
    $displayName = (string) ($accountUser?->name ?? '');
    $displayEmail = (string) ($accountUser?->email ?? '');
    $initials = collect(preg_split('/\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_substr($part, 0, 1))
        ->implode('');
    if ($initials === '') {
        $initials = 'A';
    }
    $notificationUnreadCount = (int) ($notificationUnreadCount ?? 0);
@endphp

<div class="store-account-menu__identity" role="presentation">
    <span class="store-account-menu__avatar" aria-hidden="true">{{ $initials }}</span>
    <span class="store-account-menu__identity-text">
        <span class="store-account-menu__name">{{ $displayName }}</span>
        @if ($displayEmail !== '')
            <span class="store-account-menu__email">{{ $displayEmail }}</span>
        @endif
    </span>
</div>

<div class="store-account-menu__divider" role="separator"></div>

<div class="store-account-menu__section" role="none">
    <a class="store-account-menu__item" role="menuitem" href="{{ route('customer.account') }}" @click="open = false">
        @include('theme::partials.icon', ['name' => 'layout-dashboard', 'size' => 18])
        <span>{{ __('storefront.nav.dashboard') }}</span>
    </a>
    <a class="store-account-menu__item" role="menuitem" href="{{ route('customer.profile') }}" @click="open = false">
        @include('theme::partials.icon', ['name' => 'user', 'size' => 18])
        <span>{{ __('storefront.nav.account') }}</span>
    </a>
    <a
        class="store-account-menu__item store-account-menu__item--notifications"
        role="menuitem"
        href="{{ route('customer.notifications') }}"
        @click="open = false"
        aria-label="{{ __('customer.notifications.title') }}{{ $notificationUnreadCount > 0 ? ', '.trans_choice('customer.notifications.unread_count', $notificationUnreadCount, ['count' => $notificationUnreadCount]) : '' }}"
    >
        @include('theme::partials.icon', ['name' => 'bell', 'size' => 18, 'class' => 'store-icon store-account-menu__notification-icon'])
        <span class="store-account-menu__item-copy">
            <span class="store-account-menu__item-label">{{ __('customer.notifications.title') }}</span>
        </span>
        @if ($notificationUnreadCount > 0)
            <span class="store-account-menu__notification-count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
        @endif
    </a>
</div>

@if ($canOpenAdmin)
    <div class="store-account-menu__divider" role="separator"></div>
    <div class="store-account-menu__section" role="none">
        <a class="store-account-menu__item store-account-menu__item--admin" role="menuitem" href="{{ route('admin.dashboard') }}" @click="open = false">
            @include('theme::partials.icon', ['name' => 'settings', 'size' => 18])
            <span>{{ __('storefront.nav.admin') }}</span>
        </a>
    </div>
@endif

<div class="store-account-menu__divider" role="separator"></div>

<div class="store-account-menu__section" role="none">
    <a class="store-account-menu__item store-account-menu__item--danger" role="menuitem" href="{{ route('customer.logout') }}" @click="open = false">
        @include('theme::partials.icon', ['name' => 'log-out', 'size' => 18])
        <span>{{ __('storefront.nav.logout') }}</span>
    </a>
</div>

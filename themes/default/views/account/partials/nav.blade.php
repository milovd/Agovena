@php
    $section = $accountSection ?? 'overview';
    $extraNav = collect($customerAccountNavItems ?? []);
    $purchasesExtraNav = $extraNav->filter(
        static fn ($item): bool => ($item->group ?? null) === \App\Agovena\Customer\AccountNavItem::GROUP_PURCHASES
    )->values();
    $servicesNav = $extraNav->filter(
        static fn ($item): bool => ($item->group ?? null) === \App\Agovena\Customer\AccountNavItem::GROUP_SERVICES
    )->values();
    $accountExtraNav = $extraNav->filter(
        static fn ($item): bool => ($item->group ?? null) === \App\Agovena\Customer\AccountNavItem::GROUP_ACCOUNT
    )->values();
    $primaryNav = $extraNav->filter(
        static fn ($item): bool => ($item->group ?? \App\Agovena\Customer\AccountNavItem::GROUP_PRIMARY) === \App\Agovena\Customer\AccountNavItem::GROUP_PRIMARY
    )->values();
    $purchasesActive = in_array($section, ['orders', 'invoices', 'returns'], true)
        || $purchasesExtraNav->contains(static fn ($item): bool => $item->section === $section);
    $servicesActive = $servicesNav->contains(static fn ($item): bool => $item->section === $section);
    $accountActive = in_array($section, ['profile', 'addresses', 'credits', 'subscriptions', 'security'], true)
        || $accountExtraNav->contains(static fn ($item): bool => $item->section === $section);
@endphp

<aside class="store-account__nav" aria-label="{{ __('customer.account.nav_aria') }}">
    <nav class="store-account__menu">
        <a
            class="store-account__link {{ $section === 'overview' ? 'is-active' : '' }}"
            href="{{ route('customer.account') }}"
            @if ($section === 'overview') aria-current="page" @endif
        >
            <x-ag.icon name="layout-dashboard" class="store-account__link-icon" :size="16" />
            <span>{{ __('customer.account.nav_overview') }}</span>
        </a>

        <a
            class="store-account__link {{ $section === 'tickets' ? 'is-active' : '' }}"
            href="{{ route('customer.tickets.index') }}"
            @if ($section === 'tickets') aria-current="page" @endif
        >
            <x-ag.icon name="mail" class="store-account__link-icon" :size="16" />
            <span>{{ __('customer.account.nav_tickets') }}</span>
        </a>

        <div
            class="store-account__group"
            x-data="{ open: true }"
            :class="{ 'is-open': open }"
        >
            <button
                type="button"
                class="store-account__group-toggle {{ $purchasesActive ? 'is-active' : '' }}"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="account-nav-purchases"
            >
                <span class="store-account__group-toggle-label">
                    <x-ag.icon name="shopping-bag" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_group_purchases') }}</span>
                </span>
                <x-ag.icon name="chevron-down" class="store-account__group-chevron" :size="14" />
            </button>
            <div id="account-nav-purchases" class="store-account__group-links" x-show="open">
                <a
                    class="store-account__link store-account__link--child {{ $section === 'orders' ? 'is-active' : '' }}"
                    href="{{ route('customer.orders.index') }}"
                    @if ($section === 'orders') aria-current="page" @endif
                >
                    <x-ag.icon name="package" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_orders') }}</span>
                </a>
                <a
                    class="store-account__link store-account__link--child {{ $section === 'invoices' ? 'is-active' : '' }}"
                    href="{{ route('customer.invoices.index') }}"
                    @if ($section === 'invoices') aria-current="page" @endif
                >
                    <x-ag.icon name="file-text" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_invoices') }}</span>
                </a>
                @foreach ($purchasesExtraNav as $navItem)
                    <a
                        class="store-account__link store-account__link--child {{ $section === $navItem->section ? 'is-active' : '' }}"
                        href="{{ route($navItem->route) }}"
                        @if ($section === $navItem->section) aria-current="page" @endif
                        wire:key="account-nav-{{ $navItem->id }}"
                    >
                        @if ($navItem->icon)
                            <x-ag.icon :name="$navItem->icon" class="store-account__link-icon" :size="16" />
                        @endif
                        <span>{{ __($navItem->label) }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($servicesNav->isNotEmpty())
            <div
                class="store-account__group"
                x-data="{ open: true }"
                :class="{ 'is-open': open }"
            >
                <button
                    type="button"
                    class="store-account__group-toggle {{ $servicesActive ? 'is-active' : '' }}"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="account-nav-services"
                >
                    <span class="store-account__group-toggle-label">
                        <x-ag.icon name="server" class="store-account__link-icon" :size="16" />
                        <span>{{ __('customer.account.nav_group_services') }}</span>
                    </span>
                    <x-ag.icon name="chevron-down" class="store-account__group-chevron" :size="14" />
                </button>
                <div id="account-nav-services" class="store-account__group-links" x-show="open">
                    @foreach ($servicesNav as $navItem)
                        <a
                            class="store-account__link store-account__link--child {{ $section === $navItem->section ? 'is-active' : '' }}"
                            href="{{ route($navItem->route) }}"
                            @if ($section === $navItem->section) aria-current="page" @endif
                            wire:key="account-nav-{{ $navItem->id }}"
                        >
                            @if ($navItem->icon)
                                <x-ag.icon :name="$navItem->icon" class="store-account__link-icon" :size="16" />
                            @endif
                            <span>{{ __($navItem->label) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach ($primaryNav as $navItem)
            <a
                class="store-account__link {{ $section === $navItem->section ? 'is-active' : '' }}"
                href="{{ route($navItem->route) }}"
                @if ($section === $navItem->section) aria-current="page" @endif
                wire:key="account-nav-{{ $navItem->id }}"
            >
                @if ($navItem->icon)
                    <x-ag.icon :name="$navItem->icon" class="store-account__link-icon" :size="16" />
                @endif
                <span>{{ __($navItem->label) }}</span>
            </a>
        @endforeach

        <div class="store-account__nav-divider" role="separator"></div>

        <div
            class="store-account__group"
            x-data="{ open: {{ $accountActive ? 'true' : 'false' }} }"
            :class="{ 'is-open': open }"
        >
            <button
                type="button"
                class="store-account__group-toggle {{ $accountActive ? 'is-active' : '' }}"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="account-nav-account"
            >
                <span class="store-account__group-toggle-label">
                    <x-ag.icon name="settings" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_group_account') }}</span>
                </span>
                <x-ag.icon name="chevron-down" class="store-account__group-chevron" :size="14" />
            </button>
            <div id="account-nav-account" class="store-account__group-links" x-show="open">
                <a
                    class="store-account__link store-account__link--child {{ in_array($section, ['profile', 'addresses'], true) ? 'is-active' : '' }}"
                    href="{{ route('customer.profile') }}"
                    @if (in_array($section, ['profile', 'addresses'], true)) aria-current="page" @endif
                >
                    <x-ag.icon name="users" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_settings') }}</span>
                </a>
                <a
                    class="store-account__link store-account__link--child {{ $section === 'security' ? 'is-active' : '' }}"
                    href="{{ route('customer.security') }}"
                    @if ($section === 'security') aria-current="page" @endif
                >
                    <x-ag.icon name="shield" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_security') }}</span>
                </a>
                <a
                    class="store-account__link store-account__link--child {{ $section === 'credits' ? 'is-active' : '' }}"
                    href="{{ route('customer.credits') }}"
                    @if ($section === 'credits') aria-current="page" @endif
                >
                    <x-ag.icon name="coins" class="store-account__link-icon" :size="16" />
                    <span>{{ __('customer.account.nav_credits') }}</span>
                </a>
                @foreach ($accountExtraNav as $navItem)
                    <a
                        class="store-account__link store-account__link--child {{ $section === $navItem->section ? 'is-active' : '' }}"
                        href="{{ route($navItem->route) }}"
                        @if ($section === $navItem->section) aria-current="page" @endif
                        wire:key="account-nav-{{ $navItem->id }}"
                    >
                        @if ($navItem->icon)
                            <x-ag.icon :name="$navItem->icon" class="store-account__link-icon" :size="16" />
                        @endif
                        <span>{{ __($navItem->label) }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <a class="store-account__link store-account__link--muted" href="{{ route('customer.logout') }}">
            <x-ag.icon name="log-out" class="store-account__link-icon" :size="16" />
            <span>{{ __('customer.auth.logout') }}</span>
        </a>
    </nav>
</aside>

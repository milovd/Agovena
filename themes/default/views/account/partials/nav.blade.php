@php
    $section = $accountSection ?? 'overview';
    $extraNav = $customerAccountNavItems ?? [];
@endphp

<aside class="store-account__nav" aria-label="{{ __('customer.account.nav_aria') }}">
    <nav class="store-account__menu">
        <a
            class="store-account__link {{ $section === 'overview' ? 'is-active' : '' }}"
            href="{{ route('customer.account') }}"
            @if ($section === 'overview') aria-current="page" @endif
        >{{ __('customer.account.nav_overview') }}</a>
        <a
            class="store-account__link {{ $section === 'orders' ? 'is-active' : '' }}"
            href="{{ route('customer.orders.index') }}"
            @if ($section === 'orders') aria-current="page" @endif
        >{{ __('customer.account.nav_orders') }}</a>
        <a
            class="store-account__link {{ $section === 'invoices' ? 'is-active' : '' }}"
            href="{{ route('customer.invoices.index') }}"
            @if ($section === 'invoices') aria-current="page" @endif
        >{{ __('customer.account.nav_invoices') }}</a>
        @foreach ($extraNav as $navItem)
            <a
                class="store-account__link {{ $section === $navItem->section ? 'is-active' : '' }}"
                href="{{ route($navItem->route) }}"
                @if ($section === $navItem->section) aria-current="page" @endif
                wire:key="account-nav-{{ $navItem->id }}"
            >{{ __($navItem->label) }}</a>
        @endforeach
        <a
            class="store-account__link {{ $section === 'addresses' ? 'is-active' : '' }}"
            href="{{ route('customer.addresses') }}"
            @if ($section === 'addresses') aria-current="page" @endif
        >{{ __('customer.account.nav_addresses') }}</a>
        <a
            class="store-account__link {{ $section === 'profile' ? 'is-active' : '' }}"
            href="{{ route('customer.profile') }}"
            @if ($section === 'profile') aria-current="page" @endif
        >{{ __('customer.account.nav_profile') }}</a>
        <a class="store-account__link store-account__link--muted" href="{{ route('customer.logout') }}">
            {{ __('customer.auth.logout') }}
        </a>
    </nav>
</aside>

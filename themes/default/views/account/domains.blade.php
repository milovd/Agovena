<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('domains::customer.title')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="globe" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('domains::customer.title') }}</h1>
                    <p class="store-support-hero__lede">{{ __('domains::customer.lede') }}</p>
                </div>
            </div>
        </header>

        @if ($registrations->isEmpty())
            <p class="store-muted">{{ __('domains::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($registrations as $registration)
                    <li class="store-order-items__row" wire:key="customer-domain-{{ $registration->id }}">
                        <div>
                            <strong>{{ $registration->domain_name ?? __('domains::customer.awaiting_domain') }}</strong>
                            <p>{{ __('domains::customer.status') }}: {{ __('domains::status.'.$registration->status->value) }}</p>
                            @if ($registration->expires_at)
                                <p>{{ __('domains::customer.expires') }}: {{ $registration->expires_at->toDateString() }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.credits.heading')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="coins" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('customer.credits.heading') }}</h1>
                    <p class="store-support-hero__lede">{{ __('customer.credits.lede') }}</p>
                </div>
            </div>
            <strong class="store-support-hero__balance">{{ \App\Support\MoneyFormatter::format($balance) }}</strong>
        </header>

        @forelse ($entries as $entry)
            <div class="store-account-row">
                <span><strong>{{ __('customer.credits.'.$entry->entry_type) }}</strong><small>{{ $entry->reason }}</small></span>
                <span>{{ $entry->entry_type === 'debit' ? '−' : '+' }}{{ \App\Support\MoneyFormatter::format(\App\Agovena\Money\Money::of($entry->amount, $balance->currency)) }}</span>
            </div>
        @empty
            <p class="store-note">{{ __('customer.credits.empty') }}</p>
        @endforelse
        {{ $entries->links() }}
    </section>
</div>

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])
    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">{{ __('customer.credits.heading') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.credits.lede') }}</p>
            </div>
            <strong>{{ \App\Support\MoneyFormatter::format($balance) }}</strong>
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

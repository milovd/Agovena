<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])
    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">{{ __('customer.tickets.heading') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.tickets.lede') }}</p>
            </div>
            <a class="store-btn store-btn--primary" href="{{ route('customer.tickets.create') }}">{{ __('customer.tickets.create') }}</a>
        </header>
        @forelse ($tickets as $ticket)
            <a class="store-account-row" href="{{ route('customer.tickets.show', $ticket) }}">
                <span><strong>{{ $ticket->subject }}</strong><small>{{ $ticket->number }}</small></span>
                <span>{{ __('customer.tickets.status.'.$ticket->status->value) }}</span>
            </a>
        @empty
            <p class="store-note">{{ __('customer.tickets.empty') }}</p>
        @endforelse
        {{ $tickets->links() }}
    </section>
</div>

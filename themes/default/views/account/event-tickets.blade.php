<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('events::customer.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('events::customer.lede') }}</p>
        </header>

        @if ($tickets->isEmpty())
            <p class="store-muted">{{ __('events::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($tickets as $ticket)
                    <li class="store-order-items__row" wire:key="event-ticket-{{ $ticket->id }}">
                        <div>
                            <strong>
                                <a href="{{ route('customer.event-tickets.show', $ticket) }}">{{ $ticket->event?->name }}</a>
                            </strong>
                            <p>{{ $ticket->performance?->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                            <p>{{ $ticket->number }} · {{ __('events::customer.status_'.$ticket->status->value) }}</p>
                        </div>
                        <a class="store-btn store-btn--secondary" href="{{ route('customer.event-tickets.show', $ticket) }}">
                            {{ __('events::customer.view') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

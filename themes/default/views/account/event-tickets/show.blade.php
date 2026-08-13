<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel store-ticket">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.event-tickets') }}">{{ __('events::customer.back') }}</a>
            </p>
            <h1 class="store-account-panel__title">{{ $ticket->event?->name }}</h1>
            <p class="store-account-panel__lede">{{ $ticket->number }}</p>
        </header>

        <dl class="store-ticket__meta">
            <div>
                <dt>{{ __('events::customer.venue') }}</dt>
                <dd>{{ $ticket->performance?->venue ?: $ticket->event?->venue }}</dd>
            </div>
            <div>
                <dt>{{ __('events::customer.starts_at') }}</dt>
                <dd>{{ $ticket->performance?->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt>{{ __('events::customer.type') }}</dt>
                <dd>{{ $ticket->ticketType?->name }}</dd>
            </div>
            <div>
                <dt>{{ __('events::customer.status') }}</dt>
                <dd>{{ __('events::customer.status_'.$ticket->status->value) }}</dd>
            </div>
        </dl>

        <p class="store-ticket__code" aria-label="{{ __('events::customer.code') }}">{{ $ticket->token }}</p>
        <p class="store-muted">{{ __('events::customer.code') }}</p>

        <p>
            <button class="store-btn store-btn--secondary" type="button" onclick="window.print()">
                {{ __('events::customer.print') }}
            </button>
        </p>
    </section>
</div>

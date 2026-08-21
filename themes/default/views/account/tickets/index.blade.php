<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.tickets.heading')],
            ],
        ])

        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">{{ __('customer.tickets.heading') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.tickets.lede') }}</p>
            </div>
            <a class="store-btn store-btn--primary" href="{{ route('customer.tickets.create') }}">{{ __('customer.tickets.create') }}</a>
        </header>

        @if ($tickets->isEmpty())
            <x-ag.empty :title="__('customer.tickets.empty')">
                <x-slot:icon>
                    <x-ag.icon name="mail" :size="22" />
                </x-slot:icon>
                <x-slot:actions>
                    <a class="store-btn store-btn--primary" href="{{ route('customer.tickets.create') }}">{{ __('customer.tickets.create') }}</a>
                </x-slot:actions>
            </x-ag.empty>
        @else
            <div class="store-account-card-list" role="list">
                @foreach ($tickets as $ticket)
                    @php
                        $statusClass = match ($ticket->status->value) {
                            'answered', 'closed' => 'is-success',
                            'pending' => 'is-warning',
                            default => 'is-warning',
                        };
                    @endphp
                    <a class="store-account-entry store-account-entry--link" href="{{ route('customer.tickets.show', $ticket) }}" role="listitem">
                        <div class="store-account-entry__body">
                            <p class="store-account-entry__title">{{ $ticket->subject }}</p>
                            <p class="store-account-entry__meta">{{ $ticket->number }}</p>
                            <p class="store-order-card__status {{ $statusClass }}">
                                {{ __('customer.tickets.status.'.$ticket->status->value) }}
                            </p>
                        </div>
                        <span class="store-account-entry__action" aria-hidden="true">
                            <x-ag.icon name="chevron-right" :size="16" />
                        </span>
                    </a>
                @endforeach
            </div>
            {{ $tickets->links() }}
        @endif
    </section>
</div>

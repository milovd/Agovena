<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.tickets.heading')],
            ],
        ])

        <header class="store-support-hero">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="mail" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('customer.tickets.heading') }}</h1>
                    <p class="store-support-hero__lede">{{ __('customer.tickets.lede') }}</p>
                </div>
            </div>
            <a class="store-btn store-btn--primary" href="{{ route('customer.tickets.create') }}">
                {{ __('customer.tickets.create') }}
            </a>
        </header>

        @if ($tickets->isEmpty())
            <div class="store-support-empty">
                <div class="store-support-empty__icon" aria-hidden="true">
                    <x-ag.icon name="mail" :size="28" />
                </div>
                <h2 class="store-support-empty__title">{{ __('customer.tickets.empty') }}</h2>
                <p class="store-support-empty__lede">{{ __('customer.tickets.empty_hint') }}</p>
            </div>
        @else
            <div class="store-support-list" role="list">
                @foreach ($tickets as $ticket)
                    @php
                        $statusClass = match ($ticket->status->value) {
                            'answered' => 'is-success',
                            'closed' => 'is-muted',
                            'pending' => 'is-warning',
                            default => 'is-open',
                        };
                        $when = $ticket->last_reply_at ?? $ticket->updated_at ?? $ticket->created_at;
                    @endphp
                    <a
                        class="store-support-card"
                        href="{{ route('customer.tickets.show', $ticket) }}"
                        role="listitem"
                    >
                        <span class="store-support-card__icon" aria-hidden="true">
                            <x-ag.icon name="mail" :size="18" />
                        </span>
                        <div class="store-support-card__body">
                            <p class="store-support-card__title">{{ $ticket->subject }}</p>
                            <p class="store-support-card__meta">
                                {{ $ticket->number }}
                                ·
                                {{ __('customer.tickets.priority.'.$ticket->priority->value) }}
                                @if ($when)
                                    ·
                                    {{ $when->timezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('j M Y H:i') }}
                                @endif
                            </p>
                        </div>
                        <span class="store-support-card__status {{ $statusClass }}">
                            {{ __('customer.tickets.status.'.$ticket->status->value) }}
                        </span>
                        <span class="store-support-card__chevron" aria-hidden="true">
                            <x-ag.icon name="chevron-right" :size="16" />
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="store-account-panel__pagination">{{ $tickets->links() }}</div>
        @endif
    </section>
</div>

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('subscriptions::customer.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('subscriptions::customer.lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if ($subscriptions->isEmpty())
            <p class="store-muted">{{ __('subscriptions::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($subscriptions as $subscription)
                    <li class="store-order-items__row" wire:key="customer-sub-{{ $subscription->id }}">
                        <div>
                            <strong>{{ $subscription->product?->name ?? $subscription->number }}</strong>
                            <p>{{ __('subscriptions::customer.status') }}: {{ __('subscriptions::status.'.$subscription->status->value) }}</p>
                            <p>{{ __('subscriptions::customer.period') }}:
                                {{ $subscription->current_period_start?->toDateString() }}
                                →
                                {{ $subscription->current_period_end?->toDateString() }}
                            </p>
                            @if ($subscription->cancel_at_period_end)
                                <p class="store-muted">{{ __('subscriptions::customer.ends_at_period') }}</p>
                            @endif
                        </div>
                        @if ($subscription->canCancel() && ! $subscription->cancel_at_period_end)
                            <button type="button" class="store-btn store-btn--secondary" wire:click="cancel({{ $subscription->id }})">
                                {{ __('subscriptions::customer.cancel') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

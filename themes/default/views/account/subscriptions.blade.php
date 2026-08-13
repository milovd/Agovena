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
                            <strong>
                                <a href="{{ route('customer.subscriptions.show', $subscription) }}">
                                    {{ $subscription->product?->name ?? $subscription->number }}
                                </a>
                            </strong>
                            <p>{{ __('subscriptions::customer.status') }}: {{ __('subscriptions::status.'.$subscription->status->value) }}</p>
                            <p>{{ __('subscriptions::customer.next_renewal') }}: {{ $subscription->next_billing_at?->toDateString() ?? '—' }}</p>
                            <p>{{ __('subscriptions::customer.price') }}:
                                {{ \App\Support\MoneyFormatter::format($subscription->price_amount * $subscription->quantity, $subscription->currency) }}
                            </p>
                            @if ($subscription->cancel_at_period_end)
                                <p class="store-muted">{{ __('subscriptions::customer.ends_at_period') }}</p>
                            @endif
                        </div>
                        <a class="store-btn store-btn--secondary" href="{{ route('customer.subscriptions.show', $subscription) }}">
                            {{ __('subscriptions::customer.manage') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

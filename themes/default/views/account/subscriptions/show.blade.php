<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.subscriptions') }}">{{ __('subscriptions::customer.back') }}</a>
            </p>
            <h1 class="store-account-panel__title">{{ $subscription->product?->name ?? $subscription->number }}</h1>
            <p class="store-account-panel__lede">{{ $subscription->number }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <dl class="store-account-panel__grid">
            <div>
                <dt>{{ __('subscriptions::customer.status') }}</dt>
                <dd>{{ __('subscriptions::status.'.$subscription->status->value) }}</dd>
            </div>
            <div>
                <dt>{{ __('subscriptions::customer.plan') }}</dt>
                <dd>{{ $subscription->product?->name ?? $subscription->number }}</dd>
            </div>
            <div>
                <dt>{{ __('subscriptions::customer.interval') }}</dt>
                <dd>{{ $subscription->interval_count }} × {{ __('subscriptions::interval.'.$subscription->interval->value) }}</dd>
            </div>
            <div>
                <dt>{{ __('subscriptions::customer.price') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($subscription->price_amount * $subscription->quantity, $subscription->currency) }}</dd>
            </div>
            <div>
                <dt>{{ __('subscriptions::customer.next_renewal') }}</dt>
                <dd>{{ $subscription->next_billing_at?->toDateString() ?? '—' }}</dd>
            </div>
            <div>
                <dt>{{ __('subscriptions::customer.period') }}</dt>
                <dd>{{ $subscription->current_period_start?->toDateString() }} → {{ $subscription->current_period_end?->toDateString() }}</dd>
            </div>
        </dl>

        @if ($subscription->cancel_at_period_end)
            <p class="store-muted">{{ __('subscriptions::customer.ends_at_period') }}</p>
            @if ($subscription->status->value === 'active')
                <button type="button" class="store-btn store-btn--secondary" wire:click="resume">
                    {{ __('subscriptions::customer.resume') }}
                </button>
            @endif
        @elseif ($subscription->canCancel())
            <button
                type="button"
                class="store-btn store-btn--secondary"
                wire:click="cancel"
                wire:confirm="{{ __('subscriptions::customer.cancel_confirm') }}"
            >
                {{ __('subscriptions::customer.cancel') }}
            </button>
        @endif

        @if ($pendingChange)
            <section class="store-account-panel__section">
                <h2>{{ __('subscriptions::customer.pending_plan_change') }}</h2>
                <p>{{ $pendingChange->toProduct?->name ?? __('subscriptions::customer.change_plan') }}
                    · {{ __('subscriptions::customer.timing.'.$pendingChange->timing) }}</p>
                @if ($pendingChange->order?->isAwaitingPayment())
                    <p>
                        <a class="store-btn store-btn--primary" href="{{ route('customer.orders.show', $pendingChange->order) }}">
                            {{ __('customer.account.pay_now') }}
                        </a>
                    </p>
                @endif
                <button type="button" class="store-btn store-btn--secondary" wire:click="cancelPlanChange({{ $pendingChange->id }})">
                    {{ __('subscriptions::customer.cancel_plan_change') }}
                </button>
            </section>
        @elseif ($planTargets->isNotEmpty())
            <section class="store-account-panel__section">
                <h2>{{ __('subscriptions::customer.change_plan') }}</h2>
                @foreach ($planTargets as $target)
                    <button
                        type="button"
                        class="store-btn store-btn--secondary"
                        wire:click="requestPlanChange({{ $target->to_product_id }})"
                    >
                        {{ $target->toProduct->name }}
                    </button>
                @endforeach
            </section>
        @endif

        @if (($serviceInstances ?? collect())->isNotEmpty())
            <section class="store-account-panel__section">
                <h2>{{ __('subscriptions::customer.linked_services') }}</h2>
                <ul class="store-order-items" role="list">
                    @foreach ($serviceInstances as $service)
                        <li class="store-order-items__row">
                            <div>
                                <strong>{{ $service->number }}</strong>
                                <p>{{ $service->status }}</p>
                            </div>
                            @if (\Illuminate\Support\Facades\Route::has('customer.services.show'))
                                <a href="{{ route('customer.services.show', $service->id) }}">{{ __('subscriptions::customer.view_service') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="store-account-panel__section">
            <h2>{{ __('subscriptions::customer.renewal_history') }}</h2>
            @if ($subscription->renewals->isEmpty())
                <p class="store-muted">{{ __('subscriptions::customer.renewals_empty') }}</p>
            @else
                <ul class="store-order-items" role="list">
                    @foreach ($subscription->renewals as $renewal)
                        <li class="store-order-items__row">
                            <div>
                                <strong>{{ $renewal->period_start->toDateString() }} → {{ $renewal->period_end->toDateString() }}</strong>
                                <p>{{ __('subscriptions::renewal.'.$renewal->status->value) }}</p>
                            </div>
                            @if ($renewal->order)
                                <div>
                                    <a href="{{ route('customer.orders.show', $renewal->order) }}">{{ $renewal->order->number }}</a>
                                    @if ($renewal->order->isAwaitingPayment())
                                        <a class="store-btn store-btn--primary" href="{{ route('customer.orders.show', $renewal->order) }}">{{ __('customer.account.pay_now') }}</a>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </section>
</div>

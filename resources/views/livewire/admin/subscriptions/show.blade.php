<div class="admin-page">
    <x-ag.page-header
        :heading="__('subscriptions::admin.show_title', ['number' => $subscription->number])"
        :lede="$subscription->product?->name"
    />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <section class="ag-section">
        <header class="ag-section__header">
            <h3 class="ag-section__title">{{ __('subscriptions::admin.details') }}</h3>
        </header>
        <div class="ag-section__body ag-grid ag-grid--2">
            <p><strong>{{ __('subscriptions::admin.status') }}:</strong> {{ __('subscriptions::status.'.$subscription->status->value) }}</p>
            <p><strong>{{ __('subscriptions::admin.customer') }}:</strong> {{ $subscription->customer_email }}</p>
            <p><strong>{{ __('subscriptions::admin.interval') }}:</strong>
                {{ $subscription->interval_count }} × {{ __('subscriptions::interval.'.$subscription->interval->value) }}
            </p>
            <p><strong>{{ __('subscriptions::admin.period') }}:</strong>
                {{ $subscription->current_period_start?->toDateString() }} → {{ $subscription->current_period_end?->toDateString() }}
            </p>
            <p><strong>{{ __('subscriptions::admin.next_billing') }}:</strong> {{ $subscription->next_billing_at?->toDateString() ?? '-' }}</p>
            <p><strong>{{ __('subscriptions::admin.renewal_mode') }}:</strong>
                {{ $billing->isAutomatic() ? __('subscriptions::admin.renewal_mode_automatic') : __('subscriptions::admin.renewal_mode_manual') }}
            </p>
            <p><strong>{{ __('subscriptions::admin.payment_gateway') }}:</strong> {{ $billing->gatewayLabel }}</p>
            <p><strong>{{ __('subscriptions::admin.authorization') }}:</strong>
                {{ $billing->authorizationAvailable ? __('subscriptions::admin.authorization_available') : __('subscriptions::admin.authorization_unavailable') }}
            </p>
            @if ($billing->chargeAttempts > 0)
                <p><strong>{{ __('subscriptions::admin.charge_attempts') }}:</strong> {{ $billing->chargeAttempts }}</p>
            @endif
            @if ($billing->lastError)
                <p><strong>{{ __('subscriptions::admin.last_renewal_error') }}:</strong> {{ $billing->lastError }}</p>
            @endif
            @if ($billing->nextRetryAt)
                <p><strong>{{ __('subscriptions::admin.next_retry') }}:</strong> {{ $billing->nextRetryAt->format('Y-m-d H:i') }}</p>
            @endif
            <p><strong>{{ __('subscriptions::admin.cancel_at_period_end') }}:</strong>
                {{ $subscription->cancel_at_period_end ? __('common.yes') : __('common.no') }}
            </p>
            @if ($subscription->order)
                <p><strong>{{ __('subscriptions::admin.origin_order') }}:</strong>
                    <a href="{{ route('admin.orders.show', $subscription->order) }}">{{ $subscription->order->number }}</a>
                </p>
            @endif
        </div>
    </section>

    @can('subscriptions.manage')
        <section class="ag-section">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('subscriptions::admin.actions') }}</h3>
            </header>
            <div class="ag-section__body" style="display:flex; gap:.75rem; flex-wrap:wrap;">
                @if ($subscription->canCancel() && ! $subscription->cancel_at_period_end)
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelAtPeriodEnd" wire:confirm="{{ __('subscriptions::admin.cancel_period_end_confirm') }}">
                        {{ __('subscriptions::admin.cancel_period_end') }}
                    </button>
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="cancelNow" wire:confirm="{{ __('subscriptions::admin.cancel_now_confirm') }}">
                        {{ __('subscriptions::admin.cancel_now') }}
                    </button>
                @endif
                @if ($subscription->cancel_at_period_end && $subscription->status->value === 'active')
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="resume">
                        {{ __('subscriptions::admin.resume') }}
                    </button>
                @endif
                @if ($subscription->status->value === 'active')
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="markPastDue">
                        {{ __('subscriptions::admin.mark_past_due') }}
                    </button>
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="createRenewal">
                        {{ __('subscriptions::admin.create_renewal') }}
                    </button>
                @endif
                @if ($subscription->status->value === 'past_due')
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="createRenewal">
                        {{ __('subscriptions::admin.create_renewal') }}
                    </button>
                @endif
            </div>
        </section>
    @endcan

    <section class="ag-section">
        <header class="ag-section__header">
            <h3 class="ag-section__title">{{ __('subscriptions::admin.renewals') }}</h3>
        </header>
        <div class="ag-section__body">
            @if ($subscription->renewals->isEmpty())
                <p class="ag-muted">{{ __('subscriptions::admin.renewals_empty') }}</p>
            @else
                <div class="ag-table-wrap">
                    <table class="ag-table">
                        <thead>
                            <tr>
                                <th>{{ __('subscriptions::admin.period') }}</th>
                                <th>{{ __('subscriptions::admin.status') }}</th>
                                <th>{{ __('subscriptions::admin.renewal_order') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscription->renewals as $renewal)
                                <tr wire:key="renewal-{{ $renewal->id }}">
                                    <td>{{ $renewal->period_start->toDateString() }} → {{ $renewal->period_end->toDateString() }}</td>
                                    <td>{{ __('subscriptions::renewal.'.$renewal->status->value) }}</td>
                                    <td>
                                        @if ($renewal->order)
                                            <a href="{{ route('admin.orders.show', $renewal->order) }}">{{ $renewal->order->number }}</a>
                                        @else - @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <p><a href="{{ route('admin.subscriptions.index') }}">{{ __('subscriptions::admin.back') }}</a></p>
</div>

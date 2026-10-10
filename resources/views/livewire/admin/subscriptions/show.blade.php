<div class="admin-page c-subscriptions c-subscriptions--detail">
    <x-ag.page-header
        :heading="__('subscriptions::admin.show_title', ['number' => $subscription->number])"
        :lede="$subscription->product?->name"
    />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <section class="ag-section c-subscriptions__section">
        <header class="ag-section__header">
            <div class="c-subscriptions__heading">
                <x-ag.icon-tile name="repeat" tone="blue" :size="18" />
                <h2 class="ag-section__title">{{ __('subscriptions::admin.details') }}</h2>
            </div>
        </header>
        <dl class="ag-section__body c-subscriptions__facts">
            <div><dt>{{ __('subscriptions::admin.status') }}</dt><dd><x-ag.badge :variant="match ($subscription->status->value) { 'active' => 'success', 'past_due', 'pending' => 'warning', default => 'muted' }">{{ __('subscriptions::status.'.$subscription->status->value) }}</x-ag.badge></dd></div>
            <div><dt>{{ __('subscriptions::admin.customer') }}</dt><dd>{{ $subscription->customer_email }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.interval') }}</dt><dd>{{ $subscription->interval_count }} × {{ __('subscriptions::interval.'.$subscription->interval->value) }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.period') }}</dt><dd>{{ $subscription->current_period_start?->toDateString() }} → {{ $subscription->current_period_end?->toDateString() }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.next_billing') }}</dt><dd>{{ $subscription->next_billing_at?->toDateString() ?? '-' }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.renewal_mode') }}</dt><dd>{{ $billing->isAutomatic() ? __('subscriptions::admin.renewal_mode_automatic') : __('subscriptions::admin.renewal_mode_manual') }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.payment_gateway') }}</dt><dd>{{ $billing->gatewayLabel }}</dd></div>
            <div><dt>{{ __('subscriptions::admin.authorization') }}</dt><dd>{{ $billing->authorizationAvailable ? __('subscriptions::admin.authorization_available') : __('subscriptions::admin.authorization_unavailable') }}</dd></div>
            @if ($billing->chargeAttempts > 0)
                <div><dt>{{ __('subscriptions::admin.charge_attempts') }}</dt><dd>{{ $billing->chargeAttempts }}</dd></div>
            @endif
            @if ($billing->lastError)
                <div><dt>{{ __('subscriptions::admin.last_renewal_error') }}</dt><dd>{{ $billing->lastError }}</dd></div>
            @endif
            @if ($billing->nextRetryAt)
                <div><dt>{{ __('subscriptions::admin.next_retry') }}</dt><dd>{{ $billing->nextRetryAt->format('Y-m-d H:i') }}</dd></div>
            @endif
            <div><dt>{{ __('subscriptions::admin.cancel_at_period_end') }}</dt><dd>{{ $subscription->cancel_at_period_end ? __('common.yes') : __('common.no') }}</dd></div>
            @if ($subscription->order)
                <div><dt>{{ __('subscriptions::admin.origin_order') }}</dt><dd><a href="{{ route('admin.orders.show', $subscription->order) }}">{{ $subscription->order->number }}</a></dd></div>
            @endif
        </dl>
    </section>

    @can('subscriptions.manage')
        <section class="ag-section c-subscriptions__section">
            <header class="ag-section__header">
                <div class="c-subscriptions__heading">
                    <x-ag.icon-tile name="settings" tone="amber" :size="18" />
                    <h2 class="ag-section__title">{{ __('subscriptions::admin.actions') }}</h2>
                </div>
            </header>
            <div class="ag-section__body c-subscriptions__actions">
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

    <section class="ag-section c-subscriptions__section">
        <header class="ag-section__header">
            <div class="c-subscriptions__heading">
                <x-ag.icon-tile name="repeat" tone="violet" :size="18" />
                <h2 class="ag-section__title">{{ __('subscriptions::admin.renewals') }}</h2>
            </div>
        </header>
        <div class="ag-section__body">
            @if ($subscription->renewals->isEmpty())
                <p class="ag-muted">{{ __('subscriptions::admin.renewals_empty') }}</p>
            @else
                <div class="ag-table-wrap c-subscriptions__renewals-wrap" role="region" aria-label="{{ __('subscriptions::admin.renewals') }}" tabindex="0">
                    <table class="ag-table c-subscriptions__renewals-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('subscriptions::admin.period') }}</th>
                                <th scope="col">{{ __('subscriptions::admin.status') }}</th>
                                <th scope="col">{{ __('subscriptions::admin.renewal_order') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscription->renewals as $renewal)
                                <tr wire:key="renewal-{{ $renewal->id }}">
                                    <td data-label="{{ __('subscriptions::admin.period') }}">{{ $renewal->period_start->toDateString() }} → {{ $renewal->period_end->toDateString() }}</td>
                                    <td data-label="{{ __('subscriptions::admin.status') }}"><x-ag.badge :variant="match ($renewal->status->value) { 'paid' => 'success', 'pending' => 'warning', default => 'muted' }">{{ __('subscriptions::renewal.'.$renewal->status->value) }}</x-ag.badge></td>
                                    <td data-label="{{ __('subscriptions::admin.renewal_order') }}">
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

{{-- Order detail sidebar: payments, refunds and manual payment recording. Part of livewire.admin.orders.show. --}}
<section class="ag-section" aria-labelledby="payment-heading">
    <header class="ag-section__header">
        <h3 id="payment-heading" class="ag-section__title">{{ __('admin.orders.show.payment') }}</h3>
    </header>
    <div class="ag-section__body">
        @if ($order->payment)
            <dl class="ag-dl">
                <div><dt>{{ __('common.method') }}</dt><dd>{{ $paymentGatewayLabel }}</dd></div>
                <div>
                    <dt>{{ __('common.status') }}</dt>
                    <dd>
                        <span @class([
                            'ag-badge',
                            'ag-badge--success' => $order->payment->status->value === 'paid',
                            'ag-badge--warning' => $order->payment->status->value === 'pending',
                            'ag-badge--muted' => in_array($order->payment->status->value, ['cancelled', 'failed', 'expired'], true),
                            'ag-badge--info' => $order->payment->status->value === 'partially_refunded',
                            'ag-badge--danger' => $order->payment->status->value === 'refunded',
                        ])>{{ __('admin.orders.payment_status.'.$order->payment->status->value) }}</span>
                    </dd>
                </div>
                <div><dt>{{ __('common.amount') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount, $order->payment->currency) }}</dd></div>
                @if ($order->payment->refundedAmount() > 0)
                    <div>
                        <dt>{{ __('admin.refunds.refunded') }}</dt>
                        <dd>{{ \App\Support\MoneyFormatter::format($order->payment->refundedAmount(), $order->payment->currency) }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.refunds.net_paid') }}</dt>
                        <dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount - $order->payment->refundedAmount(), $order->payment->currency) }}</dd>
                    </div>
                @endif
                @if ($order->payment->paid_at)
                    <div><dt>{{ __('admin.orders.show.paid_at') }}</dt><dd>{{ $order->payment->paid_at->toDayDateTimeString() }}</dd></div>
                @endif
                @if ($order->payment->reference)
                    <div><dt>{{ __('common.reference') }}</dt><dd>{{ $order->payment->reference }}</dd></div>
                @endif
            </dl>

            @if ($order->payment->attempts->isNotEmpty())
                <h4 class="ag-section__title" style="margin-top:1rem;">{{ __('admin.orders.show.attempts') }}</h4>
                <div class="ag-table-wrap">
                    <table class="ag-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.orders.show.gateway') }}</th>
                                <th scope="col">{{ __('admin.orders.show.provider_reference') }}</th>
                                <th scope="col">{{ __('common.status') }}</th>
                                <th scope="col">{{ __('common.created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->payment->attempts as $attempt)
                                <tr>
                                    <td>{{ $attempt->gateway_id }}</td>
                                    <td>{{ $attempt->external_id ?: '-' }}</td>
                                    <td>{{ __('admin.orders.attempt_status.'.$attempt->status->value) }}</td>
                                    <td>{{ $attempt->created_at?->toDayDateTimeString() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($order->refunds->isNotEmpty())
                <h4 class="ag-section__title" style="margin-top:1rem;">{{ __('admin.orders.show.refunds') }}</h4>
                <ul class="ag-list">
                    @foreach ($order->refunds as $refund)
                        <li>
                            {{ \App\Support\MoneyFormatter::format($refund->amount, $refund->currency) }}
                            - {{ __('admin.refunds.status.'.$refund->status->value) }}
                            @if ($refund->provider_reference)
                                ({{ $refund->provider_reference }})
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canRecord)
                @if (! $confirmingPayment)
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="startRecordPayment">
                        {{ __('admin.orders.show.record_payment') }}
                    </button>
                @else
                    <div class="ag-confirm" role="dialog" aria-labelledby="confirm-payment-title" aria-modal="true">
                        <h4 id="confirm-payment-title">{{ __('admin.orders.show.confirm_title') }}</h4>
                        <p>{{ __('admin.orders.show.confirm_text') }}</p>
                        <div class="ag-field">
                            <label class="ag-field__label" for="reference">{{ __('admin.orders.show.reference_label') }}</label>
                            <input id="reference" class="ag-input" type="text" wire:model="reference">
                        </div>
                        <div class="ag-confirm__actions">
                            <button type="button" class="ag-btn ag-btn--primary" wire:click="recordPayment">{{ __('common.confirm') }}</button>
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelRecordPayment">{{ __('common.cancel') }}</button>
                        </div>
                    </div>
                @endif
            @endif
        @else
            <p class="ag-muted">{{ __('admin.orders.show.no_payment') }}</p>
        @endif

        @if ($canCancelUnpaid)
            @if (! $confirmingCancel)
                <button type="button" class="ag-btn ag-btn--danger" wire:click="startCancelUnpaid" style="margin-top: var(--ag-space-3);">
                    {{ __('admin.orders.show.cancel_unpaid') }}
                </button>
            @else
                <div class="ag-confirm" role="dialog" aria-labelledby="confirm-cancel-title" aria-modal="true">
                    <h4 id="confirm-cancel-title">{{ __('admin.orders.show.cancel_confirm_title') }}</h4>
                    <p>{{ __('admin.orders.show.cancel_confirm_text') }}</p>
                    <div class="ag-confirm__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="cancelUnpaid">{{ __('common.confirm') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="abortCancelUnpaid">{{ __('common.cancel') }}</button>
                    </div>
                </div>
            @endif
        @endif
    </div>
</section>

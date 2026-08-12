<div class="admin-page admin-page--order">
    <x-ag.page-header :heading="__('admin.orders.show.title', ['number' => $order->number])" :lede="__('admin.orders.show.lede')">
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.index')" :label="__('admin.orders.title')" />
        </x-slot:back>
    </x-ag.page-header>

    <div class="ag-order-layout">
        <div class="ag-order-layout__main">
            <section class="ag-section" aria-labelledby="order-items-heading">
                <header class="ag-section__header">
                    <h3 id="order-items-heading" class="ag-section__title">{{ __('admin.orders.show.items') }}</h3>
                    <p class="ag-section__lede">{{ __('admin.orders.show.items_lede') }}</p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('common.product') }}</th>
                                    <th scope="col">{{ __('common.quantity') }}</th>
                                    <th scope="col">{{ __('admin.orders.show.unit') }}</th>
                                    <th scope="col">{{ __('admin.orders.show.line') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->label }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ \App\Support\MoneyFormatter::format($item->unit_amount, $item->currency) }}</td>
                                        <td>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <aside class="ag-order-layout__side">
            <section class="ag-section" aria-labelledby="order-summary-heading">
                <header class="ag-section__header">
                    <h3 id="order-summary-heading" class="ag-section__title">{{ __('admin.orders.show.summary') }}</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div>
                            <dt>{{ __('admin.orders.status_label') }}</dt>
                            <dd>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $order->status->value === 'paid',
                                    'ag-badge--warning' => $order->status->value === 'pending',
                                    'ag-badge--muted' => $order->status->value === 'cancelled',
                                ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('common.created') }}</dt>
                            <dd>{{ $order->created_at?->toDayDateTimeString() }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('common.subtotal') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $order->currency) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('common.total') }}</dt>
                            <dd><strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="customer-heading">
                <header class="ag-section__header">
                    <h3 id="customer-heading" class="ag-section__title">{{ __('common.customer') }}</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div><dt>{{ __('common.name') }}</dt><dd>{{ $order->customer_name }}</dd></div>
                        <div><dt>{{ __('common.email') }}</dt><dd><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></dd></div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="payment-heading">
                <header class="ag-section__header">
                    <h3 id="payment-heading" class="ag-section__title">{{ __('admin.orders.show.payment') }}</h3>
                </header>
                <div class="ag-section__body">
                    @if ($order->payment)
                        <dl class="ag-dl">
                            <div><dt>{{ __('common.method') }}</dt><dd>{{ __('admin.orders.method.'.$order->payment->method->value) }}</dd></div>
                            <div>
                                <dt>{{ __('common.status') }}</dt>
                                <dd>
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => $order->payment->status->value === 'cancelled',
                                    ])>{{ __('admin.orders.payment_status.'.$order->payment->status->value) }}</span>
                                </dd>
                            </div>
                            <div><dt>{{ __('common.amount') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount, $order->payment->currency) }}</dd></div>
                            @if ($order->payment->paid_at)
                                <div><dt>{{ __('admin.orders.show.paid_at') }}</dt><dd>{{ $order->payment->paid_at->toDayDateTimeString() }}</dd></div>
                            @endif
                            @if ($order->payment->reference)
                                <div><dt>{{ __('common.reference') }}</dt><dd>{{ $order->payment->reference }}</dd></div>
                            @endif
                        </dl>

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
                </div>
            </section>
        </aside>
    </div>
</div>

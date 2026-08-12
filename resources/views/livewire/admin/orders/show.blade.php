<div class="admin-page admin-page--order">
    <x-ag.page-header :heading="'Order '.$order->number" lede="Order operations and payment recording.">
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.index')" label="Orders" />
        </x-slot:back>
    </x-ag.page-header>

    <div class="ag-order-layout">
        <div class="ag-order-layout__main">
            <section class="ag-section" aria-labelledby="order-items-heading">
                <header class="ag-section__header">
                    <h3 id="order-items-heading" class="ag-section__title">Items</h3>
                    <p class="ag-section__lede">Snapshotted at checkout. Prices stay valid if catalog products change.</p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">Unit</th>
                                    <th scope="col">Line</th>
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
                    <h3 id="order-summary-heading" class="ag-section__title">Summary</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div>
                            <dt>Order status</dt>
                            <dd>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $order->status->value === 'paid',
                                    'ag-badge--warning' => $order->status->value === 'pending',
                                    'ag-badge--muted' => $order->status->value === 'cancelled',
                                ])>{{ ucfirst($order->status->value) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>Created</dt>
                            <dd>{{ $order->created_at?->toDayDateTimeString() }}</dd>
                        </div>
                        <div>
                            <dt>Subtotal</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $order->currency) }}</dd>
                        </div>
                        <div>
                            <dt>Total</dt>
                            <dd><strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="customer-heading">
                <header class="ag-section__header">
                    <h3 id="customer-heading" class="ag-section__title">Customer</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div><dt>Name</dt><dd>{{ $order->customer_name }}</dd></div>
                        <div><dt>Email</dt><dd><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></dd></div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="payment-heading">
                <header class="ag-section__header">
                    <h3 id="payment-heading" class="ag-section__title">Payment</h3>
                </header>
                <div class="ag-section__body">
                    @if ($order->payment)
                        <dl class="ag-dl">
                            <div><dt>Method</dt><dd>{{ ucfirst($order->payment->method->value) }}</dd></div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => $order->payment->status->value === 'cancelled',
                                    ])>{{ ucfirst($order->payment->status->value) }}</span>
                                </dd>
                            </div>
                            <div><dt>Amount</dt><dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount, $order->payment->currency) }}</dd></div>
                            @if ($order->payment->paid_at)
                                <div><dt>Paid at</dt><dd>{{ $order->payment->paid_at->toDayDateTimeString() }}</dd></div>
                            @endif
                            @if ($order->payment->reference)
                                <div><dt>Reference</dt><dd>{{ $order->payment->reference }}</dd></div>
                            @endif
                        </dl>

                        @if ($canRecord)
                            @if (! $confirmingPayment)
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="startRecordPayment">
                                    Record payment
                                </button>
                            @else
                                <div class="ag-confirm" role="dialog" aria-labelledby="confirm-payment-title" aria-modal="true">
                                    <h4 id="confirm-payment-title">Mark payment as received?</h4>
                                    <p>This records a manual payment and marks the order paid. It does not create a second payment.</p>
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="reference">Reference (optional)</label>
                                        <input id="reference" class="ag-input" type="text" wire:model="reference">
                                    </div>
                                    <div class="ag-confirm__actions">
                                        <button type="button" class="ag-btn ag-btn--primary" wire:click="recordPayment">Confirm</button>
                                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelRecordPayment">Cancel</button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @else
                        <p class="ag-muted">No payment record.</p>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>

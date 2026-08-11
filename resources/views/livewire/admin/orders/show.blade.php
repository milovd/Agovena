<div class="admin-page">
    <div class="admin-page__header">
        <h2 class="admin-page__heading">Order {{ $order->number }}</h2>
        <a class="ag-btn ag-btn--ghost" href="{{ route('admin.orders.index') }}">Back</a>
    </div>

    <section class="admin-panel" aria-labelledby="order-summary-heading">
        <h3 id="order-summary-heading" class="admin-panel__title">Summary</h3>
        <dl class="ag-dl">
            <div><dt>Status</dt><dd><span class="ag-badge">{{ $order->status->value }}</span></dd></div>
            <div><dt>Customer</dt><dd>{{ $order->customer_name }} &lt;{{ $order->customer_email }}&gt;</dd></div>
            <div><dt>Total</dt><dd>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</dd></div>
        </dl>
    </section>

    <section class="admin-panel" aria-labelledby="order-items-heading">
        <h3 id="order-items-heading" class="admin-panel__title">Items (snapshotted)</h3>
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">Label</th>
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
    </section>

    <section class="admin-panel" aria-labelledby="payment-heading">
        <h3 id="payment-heading" class="admin-panel__title">Payment</h3>
        @if ($order->payment)
            <dl class="ag-dl">
                <div><dt>Method</dt><dd>{{ $order->payment->method->value }}</dd></div>
                <div><dt>Status</dt><dd><span class="ag-badge">{{ $order->payment->status->value }}</span></dd></div>
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
                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="cancelRecordPayment">Cancel</button>
                        </div>
                    </div>
                @endif
            @endif
        @endif
    </section>
</div>

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            @include('theme::account.partials.breadcrumbs', [
                'items' => [
                    ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                    ['label' => __('customer.account.nav_orders'), 'url' => route('customer.orders.index')],
                    ['label' => $order->number],
                ],
            ])
            <h1 class="store-account-panel__title">{{ $order->number }}</h1>
            <p class="store-account-panel__lede">
                {{ __('customer.account.order_statuses.'.$order->status->value) }}
                ·
                {{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}
            </p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @error('pay_gateway')
            <p class="store-alert store-alert--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($order->isAwaitingPayment())
            <form class="store-account-panel__section" wire:submit="payNow">
                <h2>{{ __('customer.account.pay_now') }}</h2>
                <p>{{ __('customer.account.amount_due', ['amount' => \App\Support\MoneyFormatter::format($order->payment?->amount ?? $order->total_amount, $order->currency)]) }}</p>
                <label>
                    {{ __('customer.account.payment_method') }}
                    <select wire:model="pay_gateway">
                        @foreach ($paymentOptions as $option)
                            <option value="{{ $option['id'] }}">{{ __($option['label']) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="store-btn store-btn--primary">{{ __('customer.account.pay_now') }}</button>
            </form>
            <p>
                <button
                    type="button"
                    class="store-btn store-btn--secondary"
                    wire:click="cancelUnpaid"
                    wire:confirm="{{ __('customer.account.cancel_order_confirm') }}"
                >
                    {{ __('customer.account.cancel_order') }}
                </button>
            </p>
        @endif

        <div class="store-account-panel__grid">
            <div>
                <h2>{{ __('customer.account.items') }}</h2>
                <ul class="store-order-items" role="list">
                    @foreach ($order->items as $item)
                        <li class="store-order-items__row">
                            <div>
                                <strong>{{ $item->label }}</strong>
                                <p>{{ __('customer.account.quantity', ['count' => $item->quantity]) }}</p>
                            </div>
                            <strong>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</strong>
                        </li>
                    @endforeach
                </ul>
                <p class="store-order-items__total">
                    <span>{{ __('customer.account.total') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong>
                </p>
                @if ($order->payment)
                    <p>{{ __('customer.account.amount_paid', ['amount' => \App\Support\MoneyFormatter::format($order->payment->amount, $order->payment->currency)]) }}</p>
                    @if ($order->payment->refundedAmount() > 0)
                        <p>{{ __('customer.account.amount_refunded', ['amount' => \App\Support\MoneyFormatter::format($order->payment->refundedAmount(), $order->payment->currency)]) }}</p>
                        <p>{{ __('customer.account.net_paid', ['amount' => \App\Support\MoneyFormatter::format($order->payment->amount - $order->payment->refundedAmount(), $order->payment->currency)]) }}</p>
                    @endif
                @endif
                @if ($order->invoice)
                    <p><a href="{{ route('customer.invoices.show', $order->invoice) }}">{{ $order->invoice->number }}</a></p>
                @endif
                @foreach ($order->creditNotes as $note)
                    <p><a href="{{ route('customer.credit-notes.show', $note) }}">{{ $note->number }}</a></p>
                @endforeach
            </div>

            <div>
                <h2>{{ __('customer.account.billing') }}</h2>
                <p>{{ $order->billing_name ?: $order->customer_name }}</p>
                @if ($order->billing_company)<p>{{ $order->billing_company }}</p>@endif
                @if ($order->billing_line1)
                    <p>{{ $order->billing_line1 }}</p>
                    @if ($order->billing_line2)<p>{{ $order->billing_line2 }}</p>@endif
                    <p>{{ $order->billing_postal_code }} {{ $order->billing_city }}</p>
                    <p>{{ $order->billing_country }}</p>
                @endif
                <p>{{ $order->customer_email }}</p>
                <p>{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
            </div>

            @if (($shipments ?? []) !== [])
                <div class="store-account-panel__span">
                    <h2>{{ __('customer.account.shipments') }}</h2>
                    @foreach ($shipments as $shipment)
                        <article class="store-shipment" wire:key="shipment-{{ $loop->index }}">
                            @if ($shipment->shippingMethod)
                                <p>{{ __('customer.account.shipping_method') }}: {{ $shipment->shippingMethod }}</p>
                            @endif
                            <p>
                                <strong>{{ $shipment->statusLabel }}</strong>
                                @if ($shipment->carrierName)
                                    · {{ $shipment->carrierName }}
                                @endif
                            </p>
                            @if ($shipment->trackingNumber)
                                <p>
                                    {{ __('customer.account.tracking') }}:
                                    @if ($shipment->trackingUrl)
                                        <a href="{{ $shipment->trackingUrl }}" target="_blank" rel="noopener">{{ $shipment->trackingNumber }}</a>
                                    @else
                                        {{ $shipment->trackingNumber }}
                                    @endif
                                </p>
                            @endif
                            @if ($shipment->shippedAt)
                                <p>{{ __('customer.account.shipped_at') }}: {{ $shipment->shippedAt }}</p>
                            @endif
                            @if ($shipment->deliveredAt)
                                <p>{{ __('customer.account.delivered_at') }}: {{ $shipment->deliveredAt }}</p>
                            @endif
                            <ul>
                                @foreach ($shipment->items as $item)
                                    <li>{{ $item['quantity'] }} × {{ $item['label'] }}</li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>

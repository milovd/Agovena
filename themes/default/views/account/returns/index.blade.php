<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('shipping::returns.customer_title') }}</h1>
            <p class="store-account-panel__lede">{{ __('shipping::returns.customer_lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <div class="store-account-panel__section">
            <h2>{{ __('shipping::returns.customer_eligible_title') }}</h2>
            @if ($eligibleOrders->isEmpty())
                <p class="store-muted">{{ __('shipping::returns.customer_eligible_empty') }}</p>
            @else
                <ul class="store-order-items" role="list">
                    @foreach ($eligibleOrders as $order)
                        <li class="store-order-items__row" wire:key="eligible-order-{{ $order->id }}">
                            <div>
                                <strong>{{ $order->number }}</strong>
                                <p>{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</p>
                            </div>
                            <a class="store-btn store-btn--secondary" href="{{ route('customer.returns.create', $order) }}">
                                {{ __('shipping::returns.customer_start') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($requests->isEmpty())
            <p class="store-muted">{{ __('shipping::returns.customer_empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($requests as $request)
                    <li class="store-order-items__row" wire:key="return-{{ $request->id }}">
                        <div>
                            <strong>#{{ $request->id }} · {{ $request->order?->number }}</strong>
                            <p>{{ __('shipping::returns.statuses.'.$request->status->value) }}</p>
                            @if ($request->reason)
                                <p class="store-muted">{{ $request->reason }}</p>
                            @endif
                            <ul>
                                @foreach ($request->items as $item)
                                    <li>{{ $item->quantity }} × {{ $item->orderItem?->name ?? $item->orderItem?->label ?? ('#'.$item->order_item_id) }}</li>
                                @endforeach
                            </ul>
                            @if ($request->staff_notes)
                                <p>{{ __('shipping::returns.customer_staff_notes') }}: {{ $request->staff_notes }}</p>
                            @endif
                        </div>
                        @if ($request->status->value === 'requested')
                            <button
                                type="button"
                                class="store-btn store-btn--secondary"
                                wire:click="cancel({{ $request->id }})"
                            >
                                {{ __('shipping::returns.customer_cancel') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="store-muted">{{ __('shipping::returns.customer_refund_note') }}</p>
    </section>
</div>

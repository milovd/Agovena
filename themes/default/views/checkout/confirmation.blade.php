<div class="store-confirmation">
    <header class="store-confirmation__intro">
        <p class="store-checkout__kicker">{{ __('storefront.confirmation.kicker') }}</p>
        <h1 class="store-title">{{ __('storefront.confirmation.title') }}</h1>
        <p class="store-lede">{!! __('storefront.confirmation.lede', ['number' => '<strong>'.e($order->number).'</strong>']) !!}</p>
        <p>{{ __('customer.account.payment_status') }}:
            {{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}
        </p>
    </header>

    @if ($fulfillmentCards !== [])
        <section class="store-confirmation__cards" aria-labelledby="confirm-next">
            <h2 id="confirm-next" class="store-subtitle">{{ __('storefront.confirmation.next') }}</h2>
            <ul class="store-confirmation__grid">
                @foreach ($fulfillmentCards as $card)
                    <li class="store-confirmation__card" wire:key="fulfill-{{ $card['key'] }}">
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="store-confirmation__items" aria-labelledby="confirm-items">
        <h2 id="confirm-items" class="store-subtitle">{{ __('storefront.confirmation.items') }}</h2>
        <ul class="store-summary-lines">
            @foreach ($order->items as $item)
                <li class="store-summary-line">
                    <span class="store-summary-line__media" aria-hidden="true">
                        @if (! empty($lineImages[$item->id]))
                            <img src="{{ $lineImages[$item->id] }}" alt="">
                        @else
                            <span class="store-product-card__placeholder"></span>
                        @endif
                    </span>
                    <span class="store-summary-line__copy">
                        <span class="store-summary-line__name">{{ $item->quantity }} × {{ $item->label }}</span>
                    </span>
                    <strong class="store-summary-line__price">{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</strong>
                </li>
            @endforeach
        </ul>
        <p class="store-confirmation__total">{{ __('storefront.confirmation.total', ['amount' => \App\Support\MoneyFormatter::format($order->total_amount, $order->currency)]) }}</p>
        @if ($order->isAwaitingPayment() && auth()->check())
            <p>
                <a class="store-btn store-btn--primary" href="{{ route('customer.orders.show', $order) }}">{{ __('customer.account.continue_payment') }}</a>
            </p>
        @endif
    </section>

    <p class="store-note">{{ __('storefront.confirmation.note') }}</p>
    <a class="store-btn" href="{{ route('storefront.home') }}">{{ __('storefront.confirmation.back') }}</a>
</div>

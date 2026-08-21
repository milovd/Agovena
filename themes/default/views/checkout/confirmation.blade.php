@php
    use App\Agovena\Checkout\CheckoutStep;
    $progressItems = $progressItems ?? [];
    $currentStep = CheckoutStep::Review;
@endphp

<div class="store-checkout">
    <header class="store-checkout__intro">
        <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
    </header>

    @include('theme::checkout.partials.stepper', [
        'progressItems' => $progressItems,
        'currentLabelKey' => $currentStep->labelKey(),
        'progressPercent' => 100,
        'interactive' => false,
    ])

    <div class="store-checkout__layout">
        <div class="store-checkout__main">
            <section class="store-checkout__section store-confirmation" aria-labelledby="checkout-finish-heading">
                <p class="store-checkout__kicker">{{ __('storefront.confirmation.kicker') }}</p>
                <h2 id="checkout-finish-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.review') }}</h2>
                <p class="store-lede">{!! __('storefront.confirmation.lede', ['number' => '<strong>'.e($order->number).'</strong>']) !!}</p>
                <p>{{ __('customer.account.payment_status') }}:
                    {{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}
                </p>

                @if ($fulfillmentCards !== [])
                    <div class="store-confirmation__cards" aria-labelledby="confirm-next">
                        <h3 id="confirm-next" class="store-subtitle">{{ __('storefront.confirmation.next') }}</h3>
                        <ul class="store-confirmation__grid">
                            @foreach ($fulfillmentCards as $card)
                                <li class="store-confirmation__card" wire:key="fulfill-{{ $card['key'] }}">
                                    <h4>{{ $card['title'] }}</h4>
                                    <p>{{ $card['text'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="store-confirmation__items" aria-labelledby="confirm-items">
                    <h3 id="confirm-items" class="store-subtitle">{{ __('storefront.confirmation.items') }}</h3>
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
                </div>

                <p class="store-note">{{ __('storefront.confirmation.note') }}</p>
                <a class="store-btn store-btn--primary" href="{{ route('storefront.home') }}">{{ __('storefront.confirmation.back') }}</a>
            </section>
        </div>
    </div>
</div>

{{-- Checkout configuration review of configurable cart lines (Livewire: CheckoutPage). --}}
<section class="store-checkout__section" aria-labelledby="checkout-config-heading">
    <h2 id="checkout-config-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.configuration') }}</h2>
    <ul class="store-checkout__config">
        @foreach ($lines as $line)
            <li class="store-checkout__config-item">
                <div class="store-checkout__config-media" aria-hidden="true">
                    @if ($line->imageUrl)
                        <img src="{{ $line->imageUrl }}" alt="">
                    @else
                        <span class="store-product-card__placeholder"></span>
                    @endif
                </div>
                <div>
                    <p class="store-checkout__config-name">{{ $line->label }}</p>
                    @if ($line->optionLabels !== [])
                        <dl class="store-checkout__config-options">
                            @foreach ($line->optionLabels as $option)
                                <div>
                                    <dt>{{ $option['label'] }}</dt>
                                    <dd>{{ $option['display'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    <p class="store-note">{{ __('storefront.checkout.configuration_note') }}</p>
</section>

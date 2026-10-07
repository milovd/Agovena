{{-- Checkout delivery step: shipping address and shipping method (Livewire: CheckoutPage). --}}
<section class="store-checkout__section" aria-labelledby="checkout-delivery-heading">
    <h2 id="checkout-delivery-heading" class="store-checkout__section-title">{{ __('storefront.checkout.shipping') }}</h2>
    <label class="store-check">
        <input type="checkbox" wire:model.live="shipping_same_as_billing">
        <span>{{ __('storefront.checkout.same_as_billing') }}</span>
    </label>
    @if (! $shipping_same_as_billing)
        <div class="store-checkout__grid">
            <div class="store-field">
                <label class="store-field__label" for="shipping_name">{{ __('storefront.checkout.address_name') }}</label>
                <input id="shipping_name" class="store-input" type="text" wire:model.blur="shipping_name" required autocomplete="shipping name">
                @error('shipping_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="shipping_company">{{ __('storefront.checkout.company') }}</label>
                <input id="shipping_company" class="store-input" type="text" wire:model.blur="shipping_company" autocomplete="shipping organization">
            </div>
        </div>
        <div class="store-field store-suggest">
            <label class="store-field__label" for="shipping_line1">{{ __('storefront.checkout.line1') }}</label>
            <input
                id="shipping_line1"
                class="store-input"
                type="text"
                wire:model.live.debounce.300ms="shipping_line1"
                wire:blur="clearAddressSuggestions"
                required
                autocomplete="shipping address-line1"
                aria-autocomplete="list"
            >
            @include('theme::checkout.partials.address-suggestions', ['scope' => 'shipping'])
            @error('shipping_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="store-checkout__grid store-checkout__grid--postal">
            <div class="store-field">
                <label class="store-field__label" for="shipping_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
                <input id="shipping_postal_code" class="store-input" type="text" wire:model.blur="shipping_postal_code" required autocomplete="shipping postal-code">
                @error('shipping_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="shipping_city">{{ __('storefront.checkout.city') }}</label>
                <input id="shipping_city" class="store-input" type="text" wire:model.blur="shipping_city" required autocomplete="shipping address-level2">
                @error('shipping_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="store-field">
            <label class="store-field__label" for="shipping_country">{{ __('storefront.checkout.country') }}</label>
            <select id="shipping_country" class="store-input" wire:model.live="shipping_country" required autocomplete="shipping country">
                @foreach ($countryOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <h3 class="store-checkout__subsection">{{ __('storefront.checkout.shipping_method') }}</h3>
    <div class="store-checkout__methods" wire:loading.class="store-checkout__quotes--loading" wire:target="billing_country,shipping_country,shipping_same_as_billing">
        <p class="store-checkout__loading" wire:loading wire:target="billing_country,shipping_country,shipping_same_as_billing" role="status">{{ __('storefront.checkout.updating_shipping') }}</p>
        @forelse ($shippingQuotes as $quote)
            <label class="store-choice store-choice--row">
                <input type="radio" wire:model.live="shipping_quote_key" value="{{ $quote->key() }}">
                <span class="store-choice__copy">
                    <strong>{{ $quote->label }}</strong>
                </span>
                <span class="store-choice__price">{{ \App\Support\MoneyFormatter::format($quote->amount) }}</span>
            </label>
        @empty
            <p class="store-field__error" role="alert">{{ __('storefront.checkout.no_shipping_methods') }}</p>
            <p class="store-note">{{ __('storefront.checkout.shipping_fallback') }}</p>
        @endforelse
    </div>
    @error('shipping_quote_key') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
</section>

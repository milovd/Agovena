{{-- Checkout details step: contact, custom properties and billing address (Livewire: CheckoutPage). --}}
<section class="store-checkout__section" aria-labelledby="checkout-contact-heading">
    <h2 id="checkout-contact-heading" class="store-checkout__section-title">{{ __('storefront.checkout.contact') }}</h2>
    <div class="store-checkout__grid">
        <div class="store-field">
            <label class="store-field__label" for="customer_name">{{ __('storefront.checkout.name') }}</label>
            <input id="customer_name" class="store-input" type="text" wire:model.blur="customer_name" required autocomplete="name">
            @error('customer_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="store-field">
            <label class="store-field__label" for="customer_email">{{ __('storefront.checkout.email') }}</label>
            <input id="customer_email" class="store-input" type="email" wire:model.blur="customer_email" required autocomplete="email">
            <p class="store-field__hint">{{ __('storefront.checkout.email_hint') }}</p>
            @error('customer_email') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

@if (($propertyDefinitions ?? collect())->isNotEmpty())
    <section class="store-checkout__section" aria-labelledby="checkout-custom-heading">
        <h2 id="checkout-custom-heading" class="store-checkout__section-title">{{ __('storefront.checkout.additional_details') }}</h2>
        @include('partials.custom-property-fields', ['actor' => 'customer'])
    </section>
@endif

<section class="store-checkout__section" aria-labelledby="checkout-billing-heading">
    <h2 id="checkout-billing-heading" class="store-checkout__section-title">{{ __('storefront.checkout.billing') }}</h2>
    @if ($customerLoggedIn && $savedAddresses->isNotEmpty())
        <fieldset class="store-checkout__saved">
            <legend class="store-checkout__legend">{{ __('storefront.checkout.saved_address') }}</legend>
            <div class="store-checkout__saved-list">
                @foreach ($savedAddresses as $address)
                    <button type="button" class="store-choice store-choice--button" wire:click="applySavedAddress({{ $address->id }})">
                        <span>
                            <strong>{{ $address->label ?: $address->name }}</strong>
                            <span>{{ $address->line1 }}, {{ $address->postal_code }} {{ $address->city }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </fieldset>
    @endif
    <div class="store-checkout__grid">
        <div class="store-field">
            <label class="store-field__label" for="billing_name">{{ __('storefront.checkout.address_name') }}</label>
            <input id="billing_name" class="store-input" type="text" wire:model.blur="billing_name" required autocomplete="billing name">
            @error('billing_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="store-field">
            <label class="store-field__label" for="billing_company">{{ __('storefront.checkout.company') }}</label>
            <input id="billing_company" class="store-input" type="text" wire:model.blur="billing_company" autocomplete="billing organization">
        </div>
    </div>
    <div class="store-field store-suggest">
        <label class="store-field__label" for="billing_line1">{{ __('storefront.checkout.line1') }}</label>
        <input
            id="billing_line1"
            class="store-input"
            type="text"
            wire:model.live.debounce.300ms="billing_line1"
            wire:blur="clearAddressSuggestions"
            required
            autocomplete="billing address-line1"
            aria-autocomplete="list"
        >
        @include('theme::checkout.partials.address-suggestions', ['scope' => 'billing'])
        @error('billing_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
    </div>
    <div class="store-field">
        <label class="store-field__label" for="billing_line2">{{ __('storefront.checkout.line2') }}</label>
        <input id="billing_line2" class="store-input" type="text" wire:model.blur="billing_line2" autocomplete="billing address-line2">
    </div>
    <div class="store-checkout__grid store-checkout__grid--postal">
        <div class="store-field">
            <label class="store-field__label" for="billing_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
            <input id="billing_postal_code" class="store-input" type="text" wire:model.blur="billing_postal_code" required autocomplete="billing postal-code">
            @error('billing_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="store-field">
            <label class="store-field__label" for="billing_city">{{ __('storefront.checkout.city') }}</label>
            <input id="billing_city" class="store-input" type="text" wire:model.blur="billing_city" required autocomplete="billing address-level2">
            @error('billing_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="store-checkout__grid">
        <div class="store-field">
            <label class="store-field__label" for="billing_region">{{ __('storefront.checkout.region') }}</label>
            <input id="billing_region" class="store-input" type="text" wire:model.blur="billing_region" autocomplete="billing address-level1">
        </div>
        <div class="store-field">
            <label class="store-field__label" for="billing_country">{{ __('storefront.checkout.country') }}</label>
            <select id="billing_country" class="store-input" wire:model.live="billing_country" required autocomplete="billing country">
                @foreach ($countryOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('billing_country') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="store-field">
        <label class="store-field__label" for="billing_phone">{{ __('storefront.checkout.phone') }}</label>
        <input id="billing_phone" class="store-input" type="text" wire:model.blur="billing_phone" autocomplete="billing tel">
    </div>
    @if ($customerLoggedIn)
        <label class="store-check">
            <input type="checkbox" wire:model="save_billing_address">
            <span>{{ __('storefront.checkout.save_address') }}</span>
        </label>
    @endif
</section>

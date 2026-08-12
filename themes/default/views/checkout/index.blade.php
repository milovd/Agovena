<div class="store-checkout">
    <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
    <p class="store-lede">
        @if ($customerLoggedIn)
            {{ __('storefront.checkout.lede_account') }}
        @else
            {{ __('storefront.checkout.lede') }}
        @endif
    </p>

    @if (! $customerLoggedIn && $registrationEnabled)
        <p class="store-note">
            {{ __('customer.checkout.sign_in_prompt') }}
            <a href="{{ route('customer.login') }}">{{ __('customer.checkout.sign_in_link') }}</a>
        </p>
    @endif

    <div class="store-checkout__layout">
        <form wire:submit="placeOrder" class="store-checkout__form" novalidate>
            <input type="hidden" wire:model="idempotency_key">

            <fieldset class="store-panel">
                <legend class="store-panel__title">{{ __('storefront.checkout.contact') }}</legend>
                <div class="store-field">
                    <label class="store-field__label" for="customer_name">{{ __('storefront.checkout.name') }}</label>
                    <input id="customer_name" class="store-input" type="text" wire:model="customer_name" required autocomplete="name">
                    @error('customer_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="store-field">
                    <label class="store-field__label" for="customer_email">{{ __('storefront.checkout.email') }}</label>
                    <input id="customer_email" class="store-input" type="email" wire:model="customer_email" required autocomplete="email">
                    @error('customer_email') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset class="store-panel">
                <legend class="store-panel__title">{{ __('storefront.checkout.billing') }}</legend>
                <div class="store-field">
                    <label class="store-field__label" for="billing_name">{{ __('storefront.checkout.address_name') }}</label>
                    <input id="billing_name" class="store-input" type="text" wire:model="billing_name" required autocomplete="billing name">
                    @error('billing_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_company">{{ __('storefront.checkout.company') }}</label>
                    <input id="billing_company" class="store-input" type="text" wire:model="billing_company" autocomplete="billing organization">
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_line1">{{ __('storefront.checkout.line1') }}</label>
                    <input id="billing_line1" class="store-input" type="text" wire:model="billing_line1" required autocomplete="billing address-line1">
                    @error('billing_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_line2">{{ __('storefront.checkout.line2') }}</label>
                    <input id="billing_line2" class="store-input" type="text" wire:model="billing_line2" autocomplete="billing address-line2">
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_city">{{ __('storefront.checkout.city') }}</label>
                    <input id="billing_city" class="store-input" type="text" wire:model="billing_city" required autocomplete="billing address-level2">
                    @error('billing_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_region">{{ __('storefront.checkout.region') }}</label>
                    <input id="billing_region" class="store-input" type="text" wire:model="billing_region" autocomplete="billing address-level1">
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
                    <input id="billing_postal_code" class="store-input" type="text" wire:model="billing_postal_code" required autocomplete="billing postal-code">
                    @error('billing_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_country">{{ __('storefront.checkout.country') }}</label>
                    <select id="billing_country" class="store-input" wire:model="billing_country" required autocomplete="billing country">
                        <option value="NL">Netherlands</option>
                        <option value="BE">Belgium</option>
                        <option value="DE">Germany</option>
                        <option value="FR">France</option>
                        <option value="GB">United Kingdom</option>
                        <option value="US">United States</option>
                    </select>
                    @error('billing_country') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <label class="store-field__label" for="billing_phone">{{ __('storefront.checkout.phone') }}</label>
                    <input id="billing_phone" class="store-input" type="text" wire:model="billing_phone" autocomplete="billing tel">
                </div>
                @if ($customerLoggedIn)
                    <label class="store-check">
                        <input type="checkbox" wire:model="save_billing_address">
                        <span>{{ __('storefront.checkout.save_address') }}</span>
                    </label>
                @endif
            </fieldset>

            @if ($requiresShipping)
                <fieldset class="store-panel">
                    <legend class="store-panel__title">{{ __('storefront.checkout.shipping') }}</legend>
                    <label class="store-check">
                        <input type="checkbox" wire:model.live="shipping_same_as_billing">
                        <span>{{ __('storefront.checkout.same_as_billing') }}</span>
                    </label>

                    @if (! $shipping_same_as_billing)
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_name">{{ __('storefront.checkout.address_name') }}</label>
                            <input id="shipping_name" class="store-input" type="text" wire:model="shipping_name" required autocomplete="shipping name">
                            @error('shipping_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_line1">{{ __('storefront.checkout.line1') }}</label>
                            <input id="shipping_line1" class="store-input" type="text" wire:model="shipping_line1" required autocomplete="shipping address-line1">
                            @error('shipping_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_city">{{ __('storefront.checkout.city') }}</label>
                            <input id="shipping_city" class="store-input" type="text" wire:model="shipping_city" required autocomplete="shipping address-level2">
                            @error('shipping_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
                            <input id="shipping_postal_code" class="store-input" type="text" wire:model="shipping_postal_code" required autocomplete="shipping postal-code">
                            @error('shipping_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_country">{{ __('storefront.checkout.country') }}</label>
                            <select id="shipping_country" class="store-input" wire:model="shipping_country" required autocomplete="shipping country">
                                <option value="NL">Netherlands</option>
                                <option value="BE">Belgium</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                                <option value="GB">United Kingdom</option>
                                <option value="US">United States</option>
                            </select>
                        </div>
                    @endif

                    <div class="store-field" style="margin-top: 1rem;">
                        <fieldset>
                            <legend class="store-field__label">{{ __('storefront.checkout.shipping_method') }}</legend>
                            @forelse ($shippingQuotes as $quote)
                                <label class="store-check">
                                    <input type="radio" wire:model.live="shipping_method_id" value="{{ $quote->methodId }}">
                                    <span>
                                        {{ $quote->label }}
                                        — {{ \App\Support\MoneyFormatter::format($quote->amount) }}
                                    </span>
                                </label>
                            @empty
                                <p class="store-field__error" role="alert">{{ __('storefront.checkout.no_shipping_methods') }}</p>
                            @endforelse
                            @error('shipping_method_id') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </fieldset>
                    </div>
                </fieldset>
            @endif

            <fieldset class="store-panel">
                <legend class="store-panel__title">{{ __('storefront.checkout.payment') }}</legend>
                @foreach ($paymentOptions as $option)
                    <label class="store-check" wire:key="pay-{{ $option['id'] }}">
                        <input type="radio" wire:model="payment_method" value="{{ $option['id'] }}">
                        <span>{{ __($option['label']) }}</span>
                    </label>
                @endforeach
                @error('payment_method') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            @error('cart') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror

            <button type="submit" class="store-btn store-btn--primary store-btn--block" wire:loading.attr="disabled">{{ __('storefront.checkout.place_order') }}</button>
        </form>

        <aside class="store-summary" aria-label="{{ __('storefront.cart.summary_aria') }}">
            <h2 class="store-summary__title">{{ __('storefront.checkout.order_summary') }}</h2>
            <ul class="store-checkout__summary">
                @foreach ($lines as $line)
                    <li>
                        <span>{{ $line->quantity }} × {{ $line->label }}</span>
                        <strong>{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</strong>
                    </li>
                @endforeach
            </ul>
            <p class="store-cart__subtotal">
                {{ __('storefront.checkout.subtotal') }}
                <strong>{{ \App\Support\MoneyFormatter::format($subtotal) }}</strong>
            </p>
            @if ($requiresShipping)
                <p class="store-cart__subtotal">
                    {{ __('storefront.checkout.shipping_cost') }}
                    <strong>
                        @if ($shippingTotal)
                            {{ \App\Support\MoneyFormatter::format($shippingTotal) }}
                        @else
                            —
                        @endif
                    </strong>
                </p>
            @endif
            <p class="store-cart__subtotal">
                {{ __('storefront.checkout.total') }}
                <strong>{{ \App\Support\MoneyFormatter::format($orderTotal ?? $subtotal) }}</strong>
            </p>
        </aside>
    </div>
</div>

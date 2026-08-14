@php
    use App\Agovena\Checkout\CheckoutStep;
    $due = $amountDue ?? $orderTotal ?? $subtotal;
@endphp

<div class="store-checkout">
    <header class="store-checkout__intro">
        <p class="store-checkout__kicker">{{ __('storefront.checkout.kicker') }}</p>
        <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
        @if (! $customerLoggedIn && $registrationEnabled)
            <p class="store-note">
                {{ __('customer.checkout.sign_in_prompt') }}
                <a href="{{ route('login') }}">{{ __('customer.checkout.sign_in_link') }}</a>
            </p>
        @endif
    </header>

    <nav class="store-stepper" aria-label="{{ __('storefront.checkout.progress_aria') }}">
        <p class="store-stepper__mobile" aria-live="polite">
            {{ __('storefront.checkout.step_of', [
                'current' => collect($progressItems)->first(fn ($item) => $item->isCurrent())?->position ?? 1,
                'total' => $progressItems[0]->total ?? count($progressItems),
                'label' => __($currentStep->labelKey()),
            ]) }}
        </p>
        <ol class="store-stepper__list">
            <li class="store-stepper__item store-stepper__item--preceding">
                <a class="store-stepper__link" href="{{ route('storefront.cart') }}">
                    <span class="store-stepper__mark store-stepper__mark--done" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
                    </span>
                    <span class="store-stepper__label">{{ __('storefront.cart.title') }}</span>
                </a>
            </li>
            @foreach ($progressItems as $item)
                <li class="store-stepper__item store-stepper__item--{{ $item->state }}">
                    @if ($item->isCompleted())
                        <button type="button" class="store-stepper__link" wire:click="goToStep('{{ $item->step->value }}')">
                            <span class="store-stepper__mark store-stepper__mark--done" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
                            </span>
                            <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                            <span class="visually-hidden">{{ __('storefront.checkout.step_completed') }}</span>
                        </button>
                    @elseif ($item->isCurrent())
                        <span class="store-stepper__link" aria-current="step">
                            <span class="store-stepper__mark" aria-hidden="true">{{ $item->position }}</span>
                            <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                            <span class="visually-hidden">{{ __('storefront.checkout.step_current') }}</span>
                        </span>
                    @else
                        <span class="store-stepper__link store-stepper__link--upcoming">
                            <span class="store-stepper__mark" aria-hidden="true">{{ $item->position }}</span>
                            <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                            <span class="visually-hidden">{{ __('storefront.checkout.step_upcoming') }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
        <div class="store-stepper__bar" aria-hidden="true">
            <span class="store-stepper__bar-fill" style="width: {{ (int) round((($currentStep === CheckoutStep::Review ? count($progressItems) : (collect($progressItems)->first(fn ($item) => $item->isCurrent())?->position ?? 1) - 1) / max(1, count($progressItems))) * 100) }}%"></span>
        </div>
    </nav>

    <div class="store-checkout__layout">
        <div class="store-checkout__main">
            @if ($errors->any())
                <div class="store-checkout__errors" role="alert" tabindex="-1">
                    <p class="store-checkout__errors-title">{{ __('storefront.checkout.fix_errors') }}</p>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($currentStep === CheckoutStep::Details)
                <section class="store-checkout__section" aria-labelledby="checkout-details-heading">
                    <h2 id="checkout-details-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.details') }}</h2>
                    <fieldset class="store-checkout__group">
                        <legend class="store-checkout__legend">{{ __('storefront.checkout.contact') }}</legend>
                        <div class="store-field">
                            <label class="store-field__label" for="customer_name">{{ __('storefront.checkout.name') }}</label>
                            <input id="customer_name" class="store-input" type="text" wire:model.blur="customer_name" required autocomplete="name">
                            @error('customer_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="customer_email">{{ __('storefront.checkout.email') }}</label>
                            <input id="customer_email" class="store-input" type="email" wire:model.blur="customer_email" required autocomplete="email">
                            @error('customer_email') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    @if (($propertyDefinitions ?? collect())->isNotEmpty())
                        <fieldset class="store-checkout__group">
                            <legend class="store-checkout__legend">{{ __('storefront.checkout.additional_details') }}</legend>
                            @include('partials.custom-property-fields', ['actor' => 'customer'])
                        </fieldset>
                    @endif

                    <fieldset class="store-checkout__group">
                        <legend class="store-checkout__legend">{{ __('storefront.checkout.billing') }}</legend>
                        <div class="store-field">
                            <label class="store-field__label" for="billing_name">{{ __('storefront.checkout.address_name') }}</label>
                            <input id="billing_name" class="store-input" type="text" wire:model.blur="billing_name" required autocomplete="billing name">
                            @error('billing_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="billing_company">{{ __('storefront.checkout.company') }}</label>
                            <input id="billing_company" class="store-input" type="text" wire:model.blur="billing_company" autocomplete="billing organization">
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="billing_line1">{{ __('storefront.checkout.line1') }}</label>
                            <input id="billing_line1" class="store-input" type="text" wire:model.blur="billing_line1" required autocomplete="billing address-line1">
                            @error('billing_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="store-field">
                            <label class="store-field__label" for="billing_line2">{{ __('storefront.checkout.line2') }}</label>
                            <input id="billing_line2" class="store-input" type="text" wire:model.blur="billing_line2" autocomplete="billing address-line2">
                        </div>
                        <div class="store-checkout__row">
                            <div class="store-field">
                                <label class="store-field__label" for="billing_city">{{ __('storefront.checkout.city') }}</label>
                                <input id="billing_city" class="store-input" type="text" wire:model.blur="billing_city" required autocomplete="billing address-level2">
                                @error('billing_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="store-field">
                                <label class="store-field__label" for="billing_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
                                <input id="billing_postal_code" class="store-input" type="text" wire:model.blur="billing_postal_code" required autocomplete="billing postal-code">
                                @error('billing_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
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
                    </fieldset>
                </section>
            @endif

            @if ($currentStep === CheckoutStep::Delivery)
                <section class="store-checkout__section" aria-labelledby="checkout-delivery-heading">
                    <h2 id="checkout-delivery-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.delivery') }}</h2>
                    <fieldset class="store-checkout__group">
                        <legend class="store-checkout__legend">{{ __('storefront.checkout.shipping') }}</legend>
                        <label class="store-check">
                            <input type="checkbox" wire:model.live="shipping_same_as_billing">
                            <span>{{ __('storefront.checkout.same_as_billing') }}</span>
                        </label>
                        @if (! $shipping_same_as_billing)
                            <div class="store-field">
                                <label class="store-field__label" for="shipping_name">{{ __('storefront.checkout.address_name') }}</label>
                                <input id="shipping_name" class="store-input" type="text" wire:model.blur="shipping_name" required autocomplete="shipping name">
                                @error('shipping_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="store-field">
                                <label class="store-field__label" for="shipping_line1">{{ __('storefront.checkout.line1') }}</label>
                                <input id="shipping_line1" class="store-input" type="text" wire:model.blur="shipping_line1" required autocomplete="shipping address-line1">
                                @error('shipping_line1') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="store-checkout__row">
                                <div class="store-field">
                                    <label class="store-field__label" for="shipping_city">{{ __('storefront.checkout.city') }}</label>
                                    <input id="shipping_city" class="store-input" type="text" wire:model.blur="shipping_city" required autocomplete="shipping address-level2">
                                    @error('shipping_city') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="store-field">
                                    <label class="store-field__label" for="shipping_postal_code">{{ __('storefront.checkout.postal_code') }}</label>
                                    <input id="shipping_postal_code" class="store-input" type="text" wire:model.blur="shipping_postal_code" required autocomplete="shipping postal-code">
                                    @error('shipping_postal_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
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
                    </fieldset>
                    <fieldset class="store-checkout__group">
                        <legend class="store-checkout__legend">{{ __('storefront.checkout.shipping_method') }}</legend>
                        <div wire:loading.class="store-checkout__quotes--loading" wire:target="billing_country,shipping_country,shipping_same_as_billing">
                            <p class="store-checkout__loading" wire:loading wire:target="billing_country,shipping_country,shipping_same_as_billing" role="status">{{ __('storefront.checkout.updating_shipping') }}</p>
                            @forelse ($shippingQuotes as $quote)
                                <label class="store-choice">
                                    <input type="radio" wire:model.live="shipping_quote_key" value="{{ $quote->key() }}">
                                    <span>
                                        <strong>{{ $quote->label }}</strong>
                                        <span>{{ \App\Support\MoneyFormatter::format($quote->amount) }}</span>
                                    </span>
                                </label>
                            @empty
                                <p class="store-field__error" role="alert">{{ __('storefront.checkout.no_shipping_methods') }}</p>
                                <p class="store-note">{{ __('storefront.checkout.shipping_fallback') }}</p>
                            @endforelse
                        </div>
                        @error('shipping_quote_key') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                </section>
            @endif

            @if ($currentStep === CheckoutStep::Configuration)
                <section class="store-checkout__section" aria-labelledby="checkout-config-heading">
                    <h2 id="checkout-config-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.configuration') }}</h2>
                    <ul class="store-checkout__config">
                        @foreach ($lines as $line)
                            <li>
                                <strong>{{ $line->quantity }} × {{ $line->label }}</strong>
                                @if ($line->optionLabels !== [])
                                    <ul>
                                        @foreach ($line->optionLabels as $option)
                                            <li>{{ $option['label'] }}: {{ $option['display'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="store-note">{{ __('storefront.checkout.configuration_note') }}</p>
                </section>
            @endif

            @if ($currentStep === CheckoutStep::Payment)
                <section class="store-checkout__section" aria-labelledby="checkout-payment-heading">
                    <h2 id="checkout-payment-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.payment') }}</h2>
                    <p class="store-note">{{ __('storefront.checkout.hosted_payment_note') }}</p>
                    <fieldset class="store-checkout__group">
                        <legend class="store-checkout__legend">{{ __('storefront.checkout.payment') }}</legend>
                        @forelse ($paymentOptions as $option)
                            <label class="store-choice" wire:key="pay-{{ $option['id'] }}">
                                <input type="radio" wire:model.live="payment_method" value="{{ $option['id'] }}">
                                <span>{{ __($option['label']) }}</span>
                            </label>
                        @empty
                            <p class="store-field__error" role="alert">{{ __('storefront.checkout.no_payment_methods') }}</p>
                        @endforelse
                        @error('payment_method') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                    @if (($creditBalance ?? 0) > 0)
                        <fieldset class="store-checkout__group">
                            <legend class="store-checkout__legend">{{ __('storefront.checkout.store_credit') }}</legend>
                            <label class="store-check">
                                <input type="checkbox" wire:model.live="apply_credit">
                                <span>{{ __('storefront.checkout.apply_store_credit', ['amount' => \App\Support\MoneyFormatter::format(\App\Agovena\Money\Money::of($creditBalance, $subtotal->currency))]) }}</span>
                            </label>
                        </fieldset>
                    @endif
                </section>
            @endif

            @if ($currentStep === CheckoutStep::Review)
                <section class="store-checkout__section" aria-labelledby="checkout-review-heading">
                    <h2 id="checkout-review-heading" class="store-checkout__section-title">{{ __('storefront.checkout.steps.review') }}</h2>
                    <dl class="store-checkout__review">
                        <div>
                            <dt>{{ __('storefront.checkout.contact') }}</dt>
                            <dd>{{ $customer_name }} · {{ $customer_email }}</dd>
                            <button type="button" class="store-checkout__edit" wire:click="goToStep('details')">{{ __('storefront.checkout.edit') }}</button>
                        </div>
                        <div>
                            <dt>{{ __('storefront.checkout.billing') }}</dt>
                            <dd>{{ $billing_line1 }}, {{ $billing_postal_code }} {{ $billing_city }}</dd>
                        </div>
                        @if ($requiresShipping)
                            <div>
                                <dt>{{ __('storefront.checkout.steps.delivery') }}</dt>
                                <dd>
                                    @if ($shipping_same_as_billing)
                                        {{ __('storefront.checkout.same_as_billing') }}
                                    @else
                                        {{ $shipping_line1 }}, {{ $shipping_postal_code }} {{ $shipping_city }}
                                    @endif
                                </dd>
                                <button type="button" class="store-checkout__edit" wire:click="goToStep('delivery')">{{ __('storefront.checkout.edit') }}</button>
                            </div>
                        @endif
                        <div>
                            <dt>{{ __('storefront.checkout.payment') }}</dt>
                            <dd>
                                @foreach ($paymentOptions as $option)
                                    @if ($option['id'] === $payment_method)
                                        {{ __($option['label']) }}
                                    @endif
                                @endforeach
                            </dd>
                            <button type="button" class="store-checkout__edit" wire:click="goToStep('payment')">{{ __('storefront.checkout.edit') }}</button>
                        </div>
                    </dl>
                    @error('cart') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('payment_method') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </section>
            @endif

            <div class="store-checkout__actions">
                @if ($currentStep !== CheckoutStep::Details)
                    <button type="button" class="store-btn store-btn--ghost" wire:click="back">{{ __('storefront.checkout.back') }}</button>
                @endif
                @if ($currentStep === CheckoutStep::Review)
                    <button type="button" class="store-btn store-btn--primary" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder">
                        <span wire:loading.remove wire:target="placeOrder">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="placeOrder">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @else
                    <button type="button" class="store-btn store-btn--primary" wire:click="continueStep" wire:loading.attr="disabled" wire:target="continueStep">
                        <span wire:loading.remove wire:target="continueStep">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="continueStep">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @endif
            </div>
        </div>

        <aside class="store-summary store-checkout__aside" aria-label="{{ __('storefront.cart.summary_aria') }}">
            <details class="store-checkout__summary-disclosure" open>
                <summary class="store-checkout__summary-toggle">
                    <span>{{ __('storefront.checkout.order_summary') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($due) }}</strong>
                </summary>
                <div class="store-checkout__summary-body">
                    <h2 class="store-summary__title">{{ __('storefront.checkout.order_summary') }}</h2>
                    <ul class="store-checkout__summary">
                        @foreach ($lines as $line)
                            <li>
                                <span>
                                    {{ $line->quantity }} × {{ $line->label }}
                                    @if ($line->optionLabels !== [])
                                        <small>
                                            @foreach ($line->optionLabels as $option)
                                                {{ $option['display'] }}@if (! $loop->last), @endif
                                            @endforeach
                                        </small>
                                    @endif
                                </span>
                                <strong>{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                    <form wire:submit="applyCoupon" class="store-field">
                        <label class="store-field__label" for="coupon-code">{{ __('storefront.checkout.coupon_code') }}</label>
                        <div class="store-checkout__coupon">
                            <input id="coupon-code" class="store-input" type="text" wire:model="coupon_code" @disabled($applied_coupon_code !== '')>
                            @if ($applied_coupon_code !== '')
                                <button type="button" class="store-btn store-btn--secondary" wire:click="removeCoupon">{{ __('common.remove') }}</button>
                            @else
                                <button type="submit" class="store-btn store-btn--secondary">{{ __('storefront.checkout.apply_coupon') }}</button>
                            @endif
                        </div>
                        @error('discount_code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        @if ($applied_coupon_code !== '')
                            <p class="store-note">{{ __('storefront.checkout.coupon_applied', ['code' => $applied_coupon_code]) }}</p>
                        @endif
                    </form>
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
                    @if ($discountTotal?->amount > 0)
                        <p class="store-cart__subtotal">
                            {{ __('storefront.checkout.discount') }}
                            <strong>−{{ \App\Support\MoneyFormatter::format($discountTotal) }}</strong>
                        </p>
                    @endif
                    @if ($taxTotal?->amount > 0)
                        <p class="store-cart__subtotal">
                            {{ $pricesIncludeTax ? __('storefront.checkout.tax_included') : __('storefront.checkout.tax') }}
                            <strong>{{ \App\Support\MoneyFormatter::format($taxTotal) }}</strong>
                        </p>
                    @endif
                    <p class="store-cart__subtotal store-cart__subtotal--total">
                        {{ __('storefront.checkout.total') }}
                        <strong>{{ \App\Support\MoneyFormatter::format($orderTotal ?? $subtotal) }}</strong>
                    </p>
                    @if ($creditTotal?->amount > 0)
                        <p class="store-cart__subtotal">
                            {{ __('storefront.checkout.credit_applied') }}
                            <strong>−{{ \App\Support\MoneyFormatter::format($creditTotal) }}</strong>
                        </p>
                        <p class="store-cart__subtotal store-cart__subtotal--total">
                            {{ __('storefront.checkout.amount_due') }}
                            <strong>{{ \App\Support\MoneyFormatter::format($due) }}</strong>
                        </p>
                    @endif
                </div>
            </details>
        </aside>
    </div>
</div>

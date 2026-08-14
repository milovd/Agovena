@php
    use App\Agovena\Checkout\CheckoutStep;
    $due = $amountDue ?? $orderTotal ?? $subtotal;
    $currentPosition = collect($progressItems)->first(fn ($item) => $item->isCurrent())?->position ?? 1;
    $stepTotal = $progressItems[0]->total ?? count($progressItems);
    $progressPercent = (int) round((($currentPosition - 1) / max(1, $stepTotal)) * 100);
    $selectedPaymentLabel = collect($paymentOptions)->firstWhere('id', $payment_method)['label'] ?? null;
    $deliveryProgress = collect($progressItems)->first(fn ($item) => $item->step->includesDelivery());
@endphp

<div class="store-checkout">
    <header class="store-checkout__intro">
        <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
        @if (! $customerLoggedIn && $registrationEnabled)
            <p class="store-note">
                {{ __('customer.checkout.sign_in_prompt') }}
                <a href="{{ route('login') }}">{{ __('customer.checkout.sign_in_link') }}</a>
            </p>
        @endif
    </header>

    <nav class="store-stepper" aria-label="{{ __('storefront.checkout.progress_aria') }}" data-testid="checkout-stepper">
        <div class="store-stepper__mobile">
            <p class="store-stepper__mobile-meta" aria-live="polite">
                {{ __('storefront.checkout.step_of', [
                    'current' => $currentPosition,
                    'total' => $stepTotal,
                    'label' => __($currentStep->labelKey()),
                ]) }}
            </p>
            <div class="store-stepper__bar" aria-hidden="true">
                <span class="store-stepper__bar-fill" style="width: {{ $progressPercent }}%"></span>
            </div>
        </div>
        <ol class="store-stepper__list">
            @foreach ($progressItems as $item)
                <li class="store-stepper__item store-stepper__item--{{ $item->state }}">
                    @if ($item->isCompleted())
                        <button type="button" class="store-stepper__link" wire:click="goToStep('{{ $item->step->value }}')">
                            <span class="store-stepper__mark store-stepper__mark--done" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
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
                    <div class="store-field">
                        <label class="store-field__label" for="billing_line1">{{ __('storefront.checkout.line1') }}</label>
                        <input id="billing_line1" class="store-input" type="text" wire:model.blur="billing_line1" required autocomplete="billing address-line1">
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
            @endif

            @if ($currentStep->includesDelivery())
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
                        <div class="store-field">
                            <label class="store-field__label" for="shipping_line1">{{ __('storefront.checkout.line1') }}</label>
                            <input id="shipping_line1" class="store-input" type="text" wire:model.blur="shipping_line1" required autocomplete="shipping address-line1">
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
            @endif

            @if ($currentStep->includesConfiguration())
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
            @endif

            @if ($currentStep === CheckoutStep::Payment)
                <section class="store-checkout__section" aria-labelledby="checkout-payment-heading">
                    <h2 id="checkout-payment-heading" class="store-checkout__section-title">{{ __('storefront.checkout.payment') }}</h2>
                    <p class="store-note">{{ __('storefront.checkout.hosted_payment_note') }}</p>
                    <div class="store-checkout__methods" data-testid="checkout-payment-methods">
                        @forelse ($paymentOptions as $option)
                            <label class="store-choice store-choice--row" wire:key="pay-{{ $option['id'] }}">
                                <input type="radio" wire:model.live="payment_method" value="{{ $option['id'] }}">
                                <span class="store-choice__copy">
                                    <strong>{{ __($option['label']) }}</strong>
                                </span>
                            </label>
                        @empty
                            <p class="store-field__error" role="alert">{{ __('storefront.checkout.no_payment_methods') }}</p>
                        @endforelse
                    </div>
                    @error('payment_method') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                    @if (($creditBalance ?? 0) > 0)
                        <label class="store-check store-check--panel">
                            <input type="checkbox" wire:model.live="apply_credit">
                            <span>{{ __('storefront.checkout.apply_store_credit', ['amount' => \App\Support\MoneyFormatter::format(\App\Agovena\Money\Money::of($creditBalance, $subtotal->currency))]) }}</span>
                        </label>
                    @endif
                </section>

                <section class="store-checkout__section store-checkout__confirm" aria-labelledby="checkout-confirm-heading">
                    <h2 id="checkout-confirm-heading" class="store-checkout__section-title">{{ __('storefront.checkout.confirm_title') }}</h2>
                    <dl class="store-checkout__recap">
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
                                <dt>{{ __('storefront.checkout.shipping') }}</dt>
                                <dd>
                                    @if ($shipping_same_as_billing)
                                        {{ __('storefront.checkout.same_as_billing') }}
                                    @else
                                        {{ $shipping_line1 }}, {{ $shipping_postal_code }} {{ $shipping_city }}
                                    @endif
                                </dd>
                                <button type="button" class="store-checkout__edit" wire:click="goToStep('{{ $deliveryProgress->step->value }}')">{{ __('storefront.checkout.edit') }}</button>
                            </div>
                        @endif
                        @if ($selectedPaymentLabel)
                            <div>
                                <dt>{{ __('storefront.checkout.payment') }}</dt>
                                <dd>{{ __($selectedPaymentLabel) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @error('cart') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                </section>
            @endif

            <div class="store-checkout__actions">
                @if ($currentStep !== CheckoutStep::Details)
                    <button type="button" class="store-btn store-btn--ghost" wire:click="back">{{ __('storefront.checkout.back') }}</button>
                @endif
                @if ($currentStep === CheckoutStep::Payment)
                    <button type="button" class="store-btn store-btn--primary store-btn--checkout" data-testid="checkout-submit" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder">
                        <span wire:loading.remove wire:target="placeOrder">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="placeOrder">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @else
                    <button type="button" class="store-btn store-btn--primary store-btn--checkout" data-testid="checkout-continue" wire:click="continueStep" wire:loading.attr="disabled" wire:target="continueStep">
                        <span wire:loading.remove wire:target="continueStep">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="continueStep">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @endif
            </div>
        </div>

        <aside
            class="store-summary store-checkout__aside"
            aria-label="{{ __('storefront.cart.summary_aria') }}"
            x-data="{ open: false }"
            :class="{ 'is-open': open }"
        >
            <button
                type="button"
                class="store-checkout__summary-toggle"
                @click="open = !open"
                :aria-expanded="open.toString()"
            >
                <span>{{ __('storefront.checkout.order_summary') }}</span>
                <strong>{{ \App\Support\MoneyFormatter::format($due) }}</strong>
            </button>
            <div class="store-checkout__summary-body">
                    <h2 class="store-summary__title">{{ __('storefront.checkout.order_summary') }}</h2>
                    <ul class="store-summary-lines">
                        @foreach ($lines as $line)
                            <li class="store-summary-line">
                                <span class="store-summary-line__media" aria-hidden="true">
                                    @if ($line->imageUrl)
                                        <img src="{{ $line->imageUrl }}" alt="">
                                    @else
                                        <span class="store-product-card__placeholder"></span>
                                    @endif
                                </span>
                                <span class="store-summary-line__copy">
                                    <span class="store-summary-line__name">{{ $line->label }}</span>
                                    <span class="store-summary-line__meta">{{ __('storefront.checkout.summary_qty_price', ['qty' => $line->quantity, 'price' => \App\Support\MoneyFormatter::format($line->unitPrice)]) }}</span>
                                    @if ($line->optionLabels !== [])
                                        <span class="store-summary-line__options">
                                            @foreach ($line->optionLabels as $option)
                                                {{ $option['label'] }}: {{ $option['display'] }}@if (! $loop->last); @endif
                                            @endforeach
                                        </span>
                                    @endif
                                </span>
                                <strong class="store-summary-line__price">{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</strong>
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
                    <dl class="store-totals">
                        <div>
                            <dt>{{ __('storefront.checkout.subtotal') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($subtotal) }}</dd>
                        </div>
                        @if ($discountTotal?->amount > 0)
                            <div>
                                <dt>{{ __('storefront.checkout.discount') }}</dt>
                                <dd>−{{ \App\Support\MoneyFormatter::format($discountTotal) }}</dd>
                            </div>
                        @endif
                        @if ($creditTotal?->amount > 0)
                            <div>
                                <dt>{{ __('storefront.checkout.credit_applied') }}</dt>
                                <dd>−{{ \App\Support\MoneyFormatter::format($creditTotal) }}</dd>
                            </div>
                        @endif
                        @if ($requiresShipping)
                            <div>
                                <dt>{{ __('storefront.checkout.shipping_cost') }}</dt>
                                <dd>
                                    @if ($shippingTotal)
                                        {{ \App\Support\MoneyFormatter::format($shippingTotal) }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if ($taxTotal?->amount > 0)
                            <div>
                                <dt>{{ $pricesIncludeTax ? __('storefront.checkout.tax_included') : __('storefront.checkout.tax') }}</dt>
                                <dd>{{ \App\Support\MoneyFormatter::format($taxTotal) }}</dd>
                            </div>
                        @endif
                        <div class="store-totals__total">
                            <dt>{{ __('storefront.checkout.total') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($due) }}</dd>
                        </div>
                    </dl>
                </div>
        </aside>
    </div>
</div>

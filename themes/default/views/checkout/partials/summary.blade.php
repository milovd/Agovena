{{-- Order summary sidebar: lines, coupon and totals (Livewire: CheckoutPage). Expects $due. --}}
<aside
    class="store-summary store-checkout__aside"
    aria-label="{{ __('storefront.cart.summary_aria') }}"
    x-data="storefrontCheckoutSummary"
    :class="{ 'is-open': open }"
>
    <button
        type="button"
        class="store-checkout__summary-toggle"
        @click="toggle()"
        :aria-expanded="open.toString()"
    >
        <span>{{ __('storefront.checkout.order_summary') }}</span>
        <strong>
            @if ($due !== null)
                {{ \App\Support\MoneyFormatter::format($due) }}
            @else
                -
            @endif
        </strong>
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
                    <dd>
                        @if ($subtotal !== null)
                            {{ \App\Support\MoneyFormatter::format($subtotal) }}
                        @else
                            -
                        @endif
                    </dd>
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
                            @else - @endif
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
                    <dd>
                        @if ($due !== null)
                            {{ \App\Support\MoneyFormatter::format($due) }}
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
</aside>

{{-- Checkout payment step: payment methods, renewal mode and store credit (Livewire: CheckoutPage). --}}
<section class="store-checkout__section" aria-labelledby="checkout-payment-heading">
    <h2 id="checkout-payment-heading" class="store-checkout__section-title">{{ __('storefront.checkout.payment') }}</h2>
    <p class="store-note">{{ __('storefront.checkout.hosted_payment_note') }}</p>
    <div class="store-checkout__methods" data-testid="checkout-payment-methods">
        @php
            $balanceMoney = $subtotal !== null
                ? \App\Agovena\Money\Money::of((int) ($creditBalance ?? 0), $subtotal->currency)
                : null;
            $balanceLabel = $balanceMoney !== null
                ? \App\Support\MoneyFormatter::format($balanceMoney)
                : '-';
        @endphp
        <label class="store-choice store-choice--row" wire:key="pay-account-balance">
            <input
                type="radio"
                wire:model.live="payment_method"
                value="account_balance"
            >
            <span class="store-choice__copy">
                <strong>{{ __('storefront.checkout.pay_with_account_balance') }}</strong>
                <span class="store-choice__meta">{{ __('storefront.checkout.account_balance_available', ['amount' => $balanceLabel]) }}</span>
            </span>
        </label>
        @forelse ($paymentOptions as $option)
            <label class="store-choice store-choice--row" wire:key="pay-{{ $option['id'] }}">
                <input type="radio" wire:model.live="payment_method" value="{{ $option['id'] }}">
                <span class="store-choice__copy">
                    <x-ag.payment-method-icon
                        :icon="$option['icon'] ?? null"
                        :method-id="$option['id'] ?? null"
                        :size="36"
                        class="store-choice__icon"
                    />
                    <strong>{{ __($option['label']) }}</strong>
                </span>
            </label>
        @empty
        @endforelse
    </div>
    @if ($requiresRenewalMode)
        <fieldset class="store-checkout__renewal" aria-labelledby="renewal-mode-heading">
            <legend id="renewal-mode-heading" class="store-checkout__section-title">{{ __('storefront.checkout.renewal_mode') }}</legend>
            <label class="store-choice store-choice--row">
                <input type="radio" wire:model.live="renewal_mode" value="manual">
                <span class="store-choice__copy">
                    <strong>{{ __('storefront.checkout.renewal_manual') }}</strong>
                    <span class="store-choice__meta">{{ __('storefront.checkout.renewal_manual_help') }}</span>
                </span>
            </label>
            <label class="store-choice store-choice--row">
                <input type="radio" wire:model.live="renewal_mode" value="automatic">
                <span class="store-choice__copy">
                    <strong>{{ __('storefront.checkout.renewal_automatic') }}</strong>
                    <span class="store-choice__meta">{{ __('storefront.checkout.renewal_automatic_help') }}</span>
                </span>
            </label>
            @error('renewal_mode') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </fieldset>
    @endif
    @error('payment_method') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
    @if ($customerLoggedIn && $payment_method !== 'account_balance' && (int) ($creditBalance ?? 0) > 0)
        <label class="store-check store-check--panel">
            <input type="checkbox" wire:model.live="apply_credit">
            <span>{{ __('storefront.checkout.apply_store_credit', ['amount' => $balanceLabel]) }}</span>
        </label>
    @endif
    @error('cart') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
</section>

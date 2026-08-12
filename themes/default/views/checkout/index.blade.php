<div class="store-checkout">
    <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
    <p class="store-lede">{{ __('storefront.checkout.lede') }}</p>

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
                <legend class="store-panel__title">{{ __('storefront.checkout.payment') }}</legend>
                <label class="store-check">
                    <input type="radio" wire:model="payment_method" value="manual">
                    <span>{{ __('storefront.checkout.manual_payment') }}</span>
                </label>
                @if ($developmentPayEnabled)
                    <label class="store-check">
                        <input type="radio" wire:model="payment_method" value="development">
                        <span>{{ __('storefront.checkout.development_payment') }}</span>
                    </label>
                @endif
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
                {{ __('storefront.checkout.total') }} <strong>{{ \App\Support\MoneyFormatter::format($subtotal) }}</strong>
            </p>
        </aside>
    </div>
</div>

<div class="store-cart">
    <h1 class="store-title">{{ __('storefront.cart.title') }}</h1>

    @if (empty($lines))
        <div class="store-empty" role="status">
            <p class="store-empty__title">{{ __('storefront.cart.empty_title') }}</p>
            <p class="store-empty__text">{{ __('storefront.cart.empty_text') }}</p>
            <p class="store-empty__actions">
                <a class="store-btn store-btn--primary" href="{{ route('storefront.home') }}">{{ __('storefront.cart.continue_shopping') }}</a>
            </p>
        </div>
    @else
        <div class="store-cart__layout">
            <ul class="store-cart-list">
                @foreach ($lines as $line)
                    <li class="store-cart-list__item" wire:key="cart-{{ $line->productId }}">
                        <div>
                            <p class="store-cart-list__name">{{ $line->label }}</p>
                            <p class="store-cart-list__meta">{{ \App\Support\MoneyFormatter::format($line->unitPrice) }} {{ __('storefront.cart.each') }}</p>
                        </div>
                        <div class="store-cart-list__controls">
                            <label class="visually-hidden" for="qty-{{ $line->productId }}">{{ __('storefront.cart.quantity_for', ['name' => $line->label]) }}</label>
                            <input
                                id="qty-{{ $line->productId }}"
                                class="store-input store-input--narrow"
                                type="number"
                                min="0"
                                max="99"
                                wire:model="quantities.{{ $line->productId }}"
                            >
                            <button type="button" class="store-btn" wire:click="updateLine({{ $line->productId }})">{{ __('common.update') }}</button>
                            <button type="button" class="store-btn store-btn--ghost" wire:click="removeLine({{ $line->productId }})">{{ __('common.remove') }}</button>
                        </div>
                        <p class="store-cart-list__line">{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</p>
                    </li>
                @endforeach
            </ul>

            <aside class="store-summary" aria-label="{{ __('storefront.cart.summary_aria') }}">
                <h2 class="store-summary__title">{{ __('storefront.cart.summary') }}</h2>
                <p class="store-cart__subtotal">
                    {{ __('storefront.cart.subtotal') }} <strong>{{ \App\Support\MoneyFormatter::format($subtotal) }}</strong>
                </p>
                <a class="store-btn store-btn--primary store-btn--block" href="{{ route('storefront.checkout') }}">{{ __('storefront.cart.checkout') }}</a>
                <a class="store-summary__back" href="{{ route('storefront.home') }}">{{ __('storefront.cart.continue_shopping') }}</a>
            </aside>
        </div>
    @endif
</div>

<div class="store-cart">
    <header class="store-cart__intro">
        <h1 class="store-title">{{ __('storefront.cart.title') }}</h1>
        @if (! empty($lines))
            <p class="store-cart__count">{{ trans_choice('storefront.cart.items', (int) collect($lines)->sum('quantity'), ['count' => (int) collect($lines)->sum('quantity')]) }}</p>
        @endif
    </header>

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
                    <li class="store-cart-line" wire:key="cart-{{ $line->lineKey }}">
                        <a class="store-cart-line__media" href="{{ $line->slug ? route('storefront.product', $line->slug) : route('storefront.home') }}" aria-hidden="true" tabindex="-1">
                            @if ($line->imageUrl)
                                <img src="{{ $line->imageUrl }}" alt="">
                            @else
                                <span class="store-product-card__placeholder"></span>
                            @endif
                        </a>
                        <div class="store-cart-line__body">
                            <div class="store-cart-line__copy">
                                @if ($line->slug)
                                    <a class="store-cart-line__name" href="{{ route('storefront.product', $line->slug) }}">{{ $line->label }}</a>
                                @else
                                    <p class="store-cart-line__name">{{ $line->label }}</p>
                                @endif
                                @if ($line->optionLabels !== [])
                                    <ul class="store-cart-line__options">
                                        @foreach ($line->optionLabels as $option)
                                            <li>{{ $option['label'] }}: {{ $option['display'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <p class="store-cart-line__unit">{{ \App\Support\MoneyFormatter::format($line->unitPrice) }} {{ __('storefront.cart.each') }}</p>
                            </div>
                            <div class="store-cart-line__controls">
                                <div class="store-qty" role="group" aria-label="{{ __('storefront.cart.quantity_for', ['name' => $line->label]) }}">
                                    <button type="button" class="store-qty__btn" wire:click="decrementLine(@js($line->lineKey))" @disabled($line->quantity <= 1) aria-label="{{ __('storefront.product.decrease') }}">−</button>
                                    <input
                                        id="qty-{{ $line->lineKey }}"
                                        class="store-qty__input"
                                        type="number"
                                        min="1"
                                        max="99"
                                        wire:model.blur="quantities.{{ $line->lineKey }}"
                                        wire:change="updateLine(@js($line->lineKey))"
                                        aria-label="{{ __('storefront.cart.quantity_for', ['name' => $line->label]) }}"
                                    >
                                    <button type="button" class="store-qty__btn" wire:click="incrementLine(@js($line->lineKey))" @disabled($line->quantity >= 99) aria-label="{{ __('storefront.product.increase') }}">+</button>
                                </div>
                                <p class="store-cart-line__total">{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</p>
                                <button type="button" class="store-cart-line__remove" wire:click="removeLine(@js($line->lineKey))" aria-label="{{ __('storefront.cart.remove_item', ['name' => $line->label]) }}">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                        <path d="M4 7h16"/>
                                        <path d="M9 7V5h6v2"/>
                                        <path d="M10 11v6"/>
                                        <path d="M14 11v6"/>
                                        <path d="M6 7l1 12h10l1-12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="store-summary store-cart__summary" aria-label="{{ __('storefront.cart.summary_aria') }}">
                <h2 class="store-summary__title">{{ __('storefront.cart.summary') }}</h2>
                <dl class="store-totals">
                    <div>
                        <dt>{{ __('storefront.cart.subtotal') }}</dt>
                        <dd>{{ \App\Support\MoneyFormatter::format($subtotal) }}</dd>
                    </div>
                    <div class="store-totals__total">
                        <dt>{{ __('storefront.checkout.total') }}</dt>
                        <dd>{{ \App\Support\MoneyFormatter::format($subtotal) }}</dd>
                    </div>
                </dl>
                <a class="store-btn store-btn--primary store-btn--block store-btn--checkout" href="{{ route('storefront.checkout') }}">{{ __('storefront.cart.checkout') }}</a>
                <a class="store-summary__back" href="{{ route('storefront.home') }}">{{ __('storefront.cart.continue_shopping') }}</a>
            </aside>
        </div>
    @endif
</div>

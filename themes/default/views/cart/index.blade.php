<div class="store-cart">
    <h1 class="store-title">Cart</h1>

    @if (empty($lines))
        <div class="store-empty" role="status">
            <p class="store-empty__title">Your cart is empty</p>
            <p class="store-empty__text">Browse the catalog and add items when you are ready.</p>
            <p class="store-empty__actions">
                <a class="store-btn store-btn--primary" href="{{ route('storefront.home') }}">Continue shopping</a>
            </p>
        </div>
    @else
        <ul class="store-cart-list">
            @foreach ($lines as $line)
                <li class="store-cart-list__item" wire:key="cart-{{ $line->productId }}">
                    <div>
                        <p class="store-cart-list__name">{{ $line->label }}</p>
                        <p class="store-cart-list__meta">{{ \App\Support\MoneyFormatter::format($line->unitPrice) }} each</p>
                    </div>
                    <div class="store-cart-list__controls">
                        <label class="visually-hidden" for="qty-{{ $line->productId }}">Quantity for {{ $line->label }}</label>
                        <input
                            id="qty-{{ $line->productId }}"
                            class="store-input store-input--narrow"
                            type="number"
                            min="0"
                            max="99"
                            wire:model="quantities.{{ $line->productId }}"
                        >
                        <button type="button" class="store-btn" wire:click="updateLine({{ $line->productId }})">Update</button>
                        <button type="button" class="store-btn store-btn--ghost" wire:click="removeLine({{ $line->productId }})">Remove</button>
                    </div>
                    <p class="store-cart-list__line">{{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</p>
                </li>
            @endforeach
        </ul>

        <p class="store-cart__subtotal">
            Subtotal: <strong>{{ \App\Support\MoneyFormatter::format($subtotal) }}</strong>
        </p>

        <a class="store-btn store-btn--primary" href="{{ route('storefront.checkout') }}">Checkout</a>
    @endif
</div>

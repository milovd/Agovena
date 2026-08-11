<div class="store-checkout">
    <h1 class="store-title">Checkout</h1>
    <p class="store-lede">Guest checkout — no customer account required.</p>

    <ul class="store-checkout__summary">
        @foreach ($lines as $line)
            <li>{{ $line->quantity }} × {{ $line->label }} — {{ \App\Support\MoneyFormatter::format($line->lineTotal) }}</li>
        @endforeach
    </ul>
    <p class="store-cart__subtotal">Total: <strong>{{ \App\Support\MoneyFormatter::format($subtotal) }}</strong></p>

    <form wire:submit="placeOrder" class="store-checkout__form" novalidate>
        <input type="hidden" wire:model="idempotency_key">

        <div class="store-field">
            <label class="store-field__label" for="customer_name">Name</label>
            <input id="customer_name" class="store-input" type="text" wire:model="customer_name" required autocomplete="name">
            @error('customer_name') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="store-field">
            <label class="store-field__label" for="customer_email">Email</label>
            <input id="customer_email" class="store-input" type="email" wire:model="customer_email" required autocomplete="email">
            @error('customer_email') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        @error('cart') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror

        <button type="submit" class="store-btn store-btn--primary" wire:loading.attr="disabled">Place order</button>
    </form>
</div>

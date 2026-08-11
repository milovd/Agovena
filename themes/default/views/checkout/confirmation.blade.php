<div class="store-confirmation">
    <h1 class="store-title">Thank you</h1>
    <p class="store-lede">Order <strong>{{ $order->number }}</strong> is confirmed. Payment is pending until recorded by staff.</p>

    <section aria-labelledby="confirm-items">
        <h2 id="confirm-items" class="store-subtitle">Items</h2>
        <ul>
            @foreach ($order->items as $item)
                <li>{{ $item->quantity }} × {{ $item->label }} — {{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</li>
            @endforeach
        </ul>
        <p>Total: {{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</p>
    </section>

    <p class="store-note">Customer accounts and order history are a later Core step. Keep your order number for reference.</p>
    <a class="store-btn" href="{{ route('storefront.home') }}">Back to catalog</a>
</div>

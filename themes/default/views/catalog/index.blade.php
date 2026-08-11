<div class="store-catalog">
    <h1 class="store-title">Catalog</h1>

    @if ($products->isEmpty())
        <p class="store-empty" role="status">No products are available yet.</p>
    @else
        <ul class="store-product-list">
            @foreach ($products as $product)
                <li class="store-product-list__item">
                    <a class="store-product-list__link" href="{{ route('storefront.product', $product->slug) }}">
                        <span class="store-product-list__name">{{ $product->name }}</span>
                        <span class="store-product-list__price">{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>

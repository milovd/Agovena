<ul class="store-product-grid" role="list">
    @foreach ($products as $product)
        <li class="store-product-card">
            @include('theme::components.product-card', [
                'product' => $product,
                'showExcerpt' => $showExcerpt ?? true,
            ])
        </li>
    @endforeach
</ul>

<div class="admin-page">
    <div class="admin-page__header">
        <h2 class="admin-page__heading">Products</h2>
        @can('products.create')
            <a class="ag-btn ag-btn--primary" href="{{ route('admin.products.create') }}">Create product</a>
        @endcan
    </div>

    @if ($products->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">No products yet</p>
            <p class="ag-empty__text">Create a product to publish it on the storefront.</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Price</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td>{{ $product->name }}</td>
                            <td><span class="ag-badge">{{ $product->status->value }}</span></td>
                            <td>{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</td>
                            <td>
                                @can('products.update')
                                    <a class="ag-btn ag-btn--ghost" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    @endif
</div>

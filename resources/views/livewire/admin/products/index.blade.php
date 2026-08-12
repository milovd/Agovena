<div class="admin-page">
    <x-ag.page-header heading="Products" lede="Create, publish, and manage catalog products.">
        <x-slot:actions>
            @can('products.create')
                <a class="ag-btn ag-btn--primary" href="{{ route('admin.products.create') }}">Add product</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-search">Search products</label>
                <input
                    id="product-search"
                    class="ag-input ag-input--search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, SKU, slug"
                >
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-status">Status</label>
                <select id="product-status" class="ag-select" wire:model.live="status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-category">Category</label>
                <select id="product-category" class="ag-select" wire:model.live="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-sort">Sort</label>
                <select id="product-sort" class="ag-select" wire:model.live="sort">
                    <option value="newest">Newest</option>
                    <option value="updated">Recently updated</option>
                    <option value="name">Name</option>
                    <option value="price_asc">Price ↑</option>
                    <option value="price_desc">Price ↓</option>
                </select>
            </div>
        </div>
    </div>

    <div wire:loading.flex class="ag-loading" wire:target="search,status,category,sort,gotoPage,previousPage,nextPage">
        <span class="ag-loading__text">Loading products…</span>
    </div>

    @if ($products->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status || $category ? 'No matching products' : 'No products yet' }}</p>
            <p class="ag-empty__text">
                @if ($search || $status || $category)
                    Try adjusting search or filters.
                @else
                    Create a product to publish it on the storefront.
                @endif
            </p>
            @can('products.create')
                @if (! $search && ! $status && ! $category)
                    <p class="ag-empty__actions">
                        <a class="ag-btn ag-btn--primary" href="{{ route('admin.products.create') }}">Add product</a>
                    </p>
                @endif
            @endcan
        </div>
    @else
        <div class="ag-table-wrap" wire:loading.class="is-loading" wire:target="search,status,category,sort">
            <table class="ag-table ag-table--products">
                <thead>
                    <tr>
                        <th scope="col" class="ag-table__thumb-col"><span class="visually-hidden">Image</span></th>
                        <th scope="col">Product</th>
                        <th scope="col" class="ag-table__col--md">Category</th>
                        <th scope="col">Price</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="ag-table__col--lg">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td class="ag-table__thumb-col">
                                @if ($product->image_path)
                                    <img
                                        class="ag-thumb"
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}"
                                        alt=""
                                        width="40"
                                        height="40"
                                        loading="lazy"
                                    >
                                @else
                                    <span class="ag-thumb ag-thumb--empty" aria-hidden="true"></span>
                                @endif
                            </td>
                            <td>
                                <div class="ag-table__primary">
                                    <span class="ag-table__name">{{ $product->name }}</span>
                                    <span class="ag-muted">
                                        @if ($product->sku)
                                            {{ $product->sku }}
                                        @else
                                            {{ $product->slug }}
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="ag-table__col--md">{{ $product->category?->name ?? '—' }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</td>
                            <td>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $product->status->value === 'active',
                                    'ag-badge--muted' => $product->status->value === 'draft',
                                ])>{{ $product->status->value === 'active' ? 'Active' : 'Draft' }}</span>
                            </td>
                            <td class="ag-table__col--lg">
                                <span class="ag-muted" title="{{ $product->updated_at?->toDateTimeString() }}">
                                    {{ $product->updated_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('products.update')
                                        <a
                                            class="ag-icon-btn"
                                            href="{{ route('admin.products.edit', $product) }}"
                                            title="Edit product"
                                            aria-label="Edit {{ $product->name }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                    @endcan

                                    <div
                                        class="ag-menu"
                                        x-data="{ open: false }"
                                        @keydown.escape.window="open = false"
                                        @click.outside="open = false"
                                    >
                                        <button
                                            type="button"
                                            class="ag-icon-btn"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()"
                                            aria-haspopup="menu"
                                            title="More actions"
                                            aria-label="More actions for {{ $product->name }}"
                                        >
                                            <x-ag.icon name="more-horizontal" :size="16" />
                                        </button>
                                        <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                            @can('products.update')
                                                <a class="ag-menu__item" role="menuitem" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                                                @if ($product->status->value === 'active')
                                                    <button type="button" class="ag-menu__item" role="menuitem" wire:click="setStatus({{ $product->id }}, 'draft')">Set as draft</button>
                                                    <a class="ag-menu__item" role="menuitem" href="{{ route('storefront.product', $product->slug) }}" target="_blank" rel="noopener">Preview product</a>
                                                @else
                                                    <button type="button" class="ag-menu__item" role="menuitem" wire:click="setStatus({{ $product->id }}, 'active')">Set as active</button>
                                                @endif
                                            @endcan
                                            @can('products.delete')
                                                <button type="button" class="ag-menu__item ag-menu__item--danger" role="menuitem" wire:click="confirmDelete({{ $product->id }})">Delete permanently…</button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $products->links() }}</div>
    @endif

    @if ($confirmingProduct)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-product-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h3 id="delete-product-title" class="ag-modal__title">Delete {{ $confirmingProduct->name }}?</h3>
                @if ($confirmingReferenced)
                    <p class="ag-modal__text">
                        This product appears on historical orders and <strong>cannot</strong> be permanently deleted.
                        Set it to <strong>Draft</strong> instead so it is no longer sold on the storefront.
                    </p>
                    <div class="ag-modal__actions">
                        @can('products.update')
                            <button type="button" class="ag-btn ag-btn--primary" wire:click="draftAndClose({{ $confirmingProduct->id }})">Set as draft</button>
                        @endcan
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Close</button>
                    </div>
                @else
                    <p class="ag-modal__text">
                        This permanently deletes the product and its photos. This cannot be undone.
                    </p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteProduct">Delete permanently</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Cancel</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

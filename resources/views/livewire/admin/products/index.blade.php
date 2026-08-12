<div class="admin-page">
    <x-ag.page-header :heading="__('admin.products.title')" :lede="__('admin.products.lede')">
        <x-slot:actions>
            @can('products.create')
                <a class="ag-btn ag-btn--primary" href="{{ route('admin.products.create') }}">{{ __('admin.products.add') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-search">{{ __('admin.products.search_label') }}</label>
                <input
                    id="product-search"
                    class="ag-input ag-input--search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('admin.products.search_placeholder') }}"
                >
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-status">{{ __('common.status') }}</label>
                <select id="product-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.products.status_all') }}</option>
                    <option value="active">{{ __('common.active') }}</option>
                    <option value="draft">{{ __('common.draft') }}</option>
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-category">{{ __('common.category') }}</label>
                <select id="product-category" class="ag-select" wire:model.live="category">
                    <option value="">{{ __('admin.products.category_all') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="product-sort">{{ __('admin.products.sort_label') }}</label>
                <select id="product-sort" class="ag-select" wire:model.live="sort">
                    <option value="newest">{{ __('admin.products.sort.newest') }}</option>
                    <option value="updated">{{ __('admin.products.sort.updated') }}</option>
                    <option value="name">{{ __('admin.products.sort.name') }}</option>
                    <option value="price_asc">{{ __('admin.products.sort.price_asc') }}</option>
                    <option value="price_desc">{{ __('admin.products.sort.price_desc') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div wire:loading.flex class="ag-loading" wire:target="search,status,category,sort,gotoPage,previousPage,nextPage">
        <span class="ag-loading__text">{{ __('admin.products.loading') }}</span>
    </div>

    @if ($products->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status || $category ? __('admin.products.empty.filtered_title') : __('admin.products.empty.title') }}</p>
            <p class="ag-empty__text">
                @if ($search || $status || $category)
                    {{ __('admin.products.empty.filtered_text') }}
                @else
                    {{ __('admin.products.empty.text') }}
                @endif
            </p>
            @can('products.create')
                @if (! $search && ! $status && ! $category)
                    <p class="ag-empty__actions">
                        <a class="ag-btn ag-btn--primary" href="{{ route('admin.products.create') }}">{{ __('admin.products.add') }}</a>
                    </p>
                @endif
            @endcan
        </div>
    @else
        <div class="ag-table-wrap" wire:loading.class="is-loading" wire:target="search,status,category,sort">
            <table class="ag-table ag-table--products">
                <thead>
                    <tr>
                        <th scope="col" class="ag-table__thumb-col"><span class="visually-hidden">{{ __('common.image') }}</span></th>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col" class="ag-table__col--md">{{ __('common.category') }}</th>
                        <th scope="col">{{ __('common.price') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col" class="ag-table__col--lg">{{ __('common.updated') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
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
                            <td class="ag-table__col--md">{{ $product->category?->name ?? __('common.em_dash') }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($product->price_amount, $product->currency) }}</td>
                            <td>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $product->status->value === 'active',
                                    'ag-badge--muted' => $product->status->value === 'draft',
                                ])>{{ $product->status->value === 'active' ? __('common.active') : __('common.draft') }}</span>
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
                                            title="{{ __('admin.products.actions.edit') }}"
                                            aria-label="{{ __('admin.products.actions.edit_aria', ['name' => $product->name]) }}"
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
                                            title="{{ __('admin.products.actions.more') }}"
                                            aria-label="{{ __('admin.products.actions.more_aria', ['name' => $product->name]) }}"
                                        >
                                            <x-ag.icon name="more-horizontal" :size="16" />
                                        </button>
                                        <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                            @can('products.update')
                                                <a class="ag-menu__item" role="menuitem" href="{{ route('admin.products.edit', $product) }}">{{ __('common.edit') }}</a>
                                                @if ($product->status->value === 'active')
                                                    <button type="button" class="ag-menu__item" role="menuitem" wire:click="setStatus({{ $product->id }}, 'draft')">{{ __('admin.products.actions.set_draft') }}</button>
                                                    <a class="ag-menu__item" role="menuitem" href="{{ route('storefront.product', $product->slug) }}" target="_blank" rel="noopener">{{ __('admin.products.actions.preview') }}</a>
                                                @else
                                                    <button type="button" class="ag-menu__item" role="menuitem" wire:click="setStatus({{ $product->id }}, 'active')">{{ __('admin.products.actions.set_active') }}</button>
                                                @endif
                                            @endcan
                                            @can('products.delete')
                                                <button type="button" class="ag-menu__item ag-menu__item--danger" role="menuitem" wire:click="confirmDelete({{ $product->id }})">{{ __('admin.products.actions.delete_prompt') }}</button>
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
                <h3 id="delete-product-title" class="ag-modal__title">{{ __('admin.products.delete.title', ['name' => $confirmingProduct->name]) }}</h3>
                @if ($confirmingReferenced)
                    <p class="ag-modal__text">
                        {!! __('admin.products.delete.referenced_text_html') !!}
                    </p>
                    <div class="ag-modal__actions">
                        @can('products.update')
                            <button type="button" class="ag-btn ag-btn--primary" wire:click="draftAndClose({{ $confirmingProduct->id }})">{{ __('admin.products.actions.set_draft') }}</button>
                        @endcan
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.close') }}</button>
                    </div>
                @else
                    <p class="ag-modal__text">
                        {{ __('admin.products.delete.text') }}
                    </p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteProduct">{{ __('admin.products.actions.delete') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

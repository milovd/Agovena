<div class="admin-page">
    <x-ag.page-header :heading="__('inventory::admin.title')" :lede="__('inventory::admin.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="inventory-search">{{ __('inventory::admin.search_label') }}</label>
                <input
                    id="inventory-search"
                    class="ag-input ag-input--search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('inventory::admin.search_placeholder') }}"
                >
            </div>
        </div>
    </div>

    @if ($products->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('inventory::admin.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('inventory::admin.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col">{{ __('admin.products.form.sku') }}</th>
                        <th scope="col">{{ __('inventory::admin.quantity') }}</th>
                        <th scope="col">{{ __('inventory::admin.track_stock') }}</th>
                        <th scope="col">{{ __('inventory::admin.allow_oversell') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="stock-{{ $product->id }}">
                            <td><span class="ag-table__name">{{ $product->name }}</span></td>
                            <td>{{ $product->sku ?: '—' }}</td>
                            <td>
                                <input
                                    class="ag-input"
                                    type="number"
                                    min="0"
                                    wire:model="quantities.{{ $product->id }}"
                                    @disabled(! auth()->user()?->can('inventory.manage'))
                                >
                            </td>
                            <td>
                                <input type="checkbox" wire:model="trackStock.{{ $product->id }}" @disabled(! auth()->user()?->can('inventory.manage'))>
                            </td>
                            <td>
                                <input type="checkbox" wire:model="allowOversell.{{ $product->id }}" @disabled(! auth()->user()?->can('inventory.manage'))>
                            </td>
                            <td>
                                @can('inventory.manage')
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="saveStock({{ $product->id }})">
                                        {{ __('common.save') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $products->links() }}</div>
    @endif
</div>

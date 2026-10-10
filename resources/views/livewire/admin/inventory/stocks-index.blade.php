<div class="admin-page ag-list-page c-inventory">
    <x-ag.page-header :heading="__('inventory::admin.title')" :lede="__('inventory::admin.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters c-inventory__toolbar">
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
        <x-ag.empty class="c-inventory__empty" :title="__('inventory::admin.empty.title')">
            <x-slot:icon><x-ag.icon name="warehouse" :size="24" /></x-slot:icon>
            <x-slot:description>{{ __('inventory::admin.empty.text') }}</x-slot:description>
        </x-ag.empty>
    @else
        <div class="ag-table-wrap c-inventory__table-wrap" role="region" aria-label="{{ __('inventory::admin.title') }}" tabindex="0">
            <table class="ag-table c-inventory__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col">{{ __('inventory::admin.quantity') }}</th>
                        <th scope="col">{{ __('inventory::admin.track_stock') }}</th>
                        <th scope="col">{{ __('inventory::admin.allow_oversell') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="stock-{{ $product->id }}">
                            <td class="c-inventory__identity">
                                <span class="ag-table__name c-inventory__name">{{ $product->name }}</span>
                                <span class="c-inventory__sku">{{ $product->sku ?: '-' }}</span>
                            </td>
                            <td class="c-inventory__quantity">
                                <label class="c-inventory__field-label" for="stock-quantity-{{ $product->id }}">{{ __('inventory::admin.quantity') }} <span class="visually-hidden">{{ $product->name }}</span></label>
                                <input
                                    id="stock-quantity-{{ $product->id }}"
                                    class="ag-input"
                                    type="number"
                                    min="0"
                                    wire:model="quantities.{{ $product->id }}"
                                    @disabled(! auth()->user()?->can('inventory.manage'))
                                >
                            </td>
                            <td class="c-inventory__toggle">
                                <label class="c-inventory__check" for="stock-track-{{ $product->id }}">
                                    <input id="stock-track-{{ $product->id }}" type="checkbox" wire:model="trackStock.{{ $product->id }}" @disabled(! auth()->user()?->can('inventory.manage'))>
                                    <span class="c-inventory__check-label">{{ __('inventory::admin.track_stock') }}</span><span class="visually-hidden"> {{ $product->name }}</span>
                                </label>
                            </td>
                            <td class="c-inventory__toggle">
                                <label class="c-inventory__check" for="stock-oversell-{{ $product->id }}">
                                    <input id="stock-oversell-{{ $product->id }}" type="checkbox" wire:model="allowOversell.{{ $product->id }}" @disabled(! auth()->user()?->can('inventory.manage'))>
                                    <span class="c-inventory__check-label">{{ __('inventory::admin.allow_oversell') }}</span><span class="visually-hidden"> {{ $product->name }}</span>
                                </label>
                            </td>
                            <td class="ag-table__actions c-inventory__actions">
                                @can('inventory.manage')
                                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="saveStock({{ $product->id }})" wire:loading.attr="disabled" wire:target="saveStock({{ $product->id }})" aria-label="{{ __('common.save') }}: {{ $product->name }}">
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

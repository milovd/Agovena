<div class="admin-page ag-list-page admin-page--orders">
    <x-ag.page-header :heading="__('admin.orders.title')" :lede="__('admin.orders.lede')" />

    <x-ag.flash />

    <x-ag.table-toolbar filters>
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-search">{{ __('admin.orders.search_label') }}</label>
                <input id="order-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.orders.search_placeholder') }}">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-status">{{ __('admin.orders.status_label') }}</label>
                <select id="order-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.orders.status_all') }}</option>
                    @foreach ($orderStatuses as $case)
                        <option value="{{ $case->value }}">{{ __('admin.orders.status.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="payment-status">{{ __('admin.orders.payment_status_label') }}</label>
                <select id="payment-status" class="ag-select" wire:model.live="paymentStatus">
                    <option value="">{{ __('admin.orders.payment_status_all') }}</option>
                    @foreach ($paymentStatuses as $case)
                        <option value="{{ $case->value }}">{{ __('admin.orders.payment_status.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-ag.table-toolbar>

    <div wire:loading.flex class="ag-loading" wire:target="search,status,paymentStatus,gotoPage,previousPage,nextPage">
        <span class="ag-loading__text">{{ __('admin.orders.loading') }}</span>
    </div>

    @if ($bulkDeleteResult !== null)
        <div class="ag-alert {{ $bulkDeleteResult['failed'] || $bulkDeleteResult['skipped'] || $bulkDeleteResult['kept'] ? 'ag-alert--danger' : 'ag-alert--success' }}" role="status" aria-live="polite" tabindex="-1">
            {{ __('admin.orders.bulk_delete_result', $bulkDeleteResult) }}
        </div>
    @endif

    @if ($orders->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status || $paymentStatus ? __('admin.orders.empty.filtered_title') : __('admin.orders.empty.title') }}</p>
            <p class="ag-empty__text">
                {{ $search || $status || $paymentStatus ? __('admin.orders.empty.filtered_text') : __('admin.orders.empty.text') }}
            </p>
        </div>
    @else
        @php
            $displayCurrency = \App\Support\MoneyFormatter::preferredDisplayCurrency();
            $visibleIds = $orders->getCollection()->pluck('id')->map(static fn ($id) => (string) $id)->all();
            $selectedIds = array_map(static fn ($id) => is_int($id) || is_string($id) ? (string) $id : '', $selectedOrderIds);
            $allSelected = $visibleIds !== [] && array_diff($visibleIds, $selectedIds) === [];
        @endphp
        @can('orders.delete')
            @error('selectedOrderIds') <div class="ag-alert ag-alert--danger" role="alert">{{ $message }}</div> @enderror
        @endcan
        <div
            class="ag-bulk-selection__surface"
            @can('orders.delete')
                x-data="agBulkSelection"
                data-bulk-model="selectedOrderIds"
                data-bulk-visible="{{ json_encode($visibleIds) }}"
                data-bulk-count-label="{{ __('admin.orders.bulk_selected', ['count' => '__COUNT__']) }}"
                data-bulk-select-label="{{ __('admin.orders.bulk_select_page') }}"
                data-bulk-deselect-label="{{ __('admin.orders.bulk_deselect_page') }}"
            @endcan
        >
        <div class="ag-table-wrap" role="region" aria-label="{{ __('admin.orders.title') }}" tabindex="0" wire:loading.class="is-loading" wire:target="search,status,paymentStatus">
            <table class="ag-table ag-table--orders">
                <thead>
                    <tr>
                        @can('orders.delete')<th scope="col" class="ag-table__select-col"><button type="button" class="ag-bulk-selection__select" role="checkbox" aria-checked="{{ $allSelected ? 'true' : 'false' }}" @click="toggleAll" :aria-checked="ariaChecked" title="{{ __($allSelected ? 'admin.orders.bulk_deselect_page' : 'admin.orders.bulk_select_page') }}" :title="toggleLabel" aria-label="{{ __($allSelected ? 'admin.orders.bulk_deselect_page' : 'admin.orders.bulk_select_page') }}" :aria-label="toggleLabel"><x-ag.icon name="check" :size="13" /></button></th>@endcan
                        <th scope="col">{{ __('admin.orders.order_column') }} @can('orders.delete')<span class="ag-bulk-selection__count" aria-live="polite" x-text="countLabel">{{ __('admin.orders.bulk_selected', ['count' => count($selectedOrderIds)]) }}</span>@endcan</th>
                        <th scope="col">{{ __('common.customer') }}</th>
                        <th scope="col">{{ __('common.total') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col" class="ag-table__col--md">{{ __('admin.orders.payment_column') }}</th>
                        <th scope="col" class="ag-table__col--lg">{{ __('common.created') }}</th>
                        <th scope="col" class="ag-bulk-selection__header-actions"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php($formattedTotal = \App\Support\MoneyFormatter::formatDisplay($order->total_amount, $order->currency, $displayCurrency))
                        <tr wire:key="order-{{ $order->id }}">
                            @can('orders.delete')
                                <td class="ag-table__select-col"><x-ag.checkbox id="order-select-{{ $order->id }}" wire:model="selectedOrderIds" value="{{ $order->id }}" aria-label="{{ __('admin.orders.bulk_select_row', ['number' => $order->number]) }}" /></td>
                            @endcan
                            <td>
                                <div class="ag-table__primary">
                                    <a class="ag-table__name" href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>
                                    <span class="ag-table__mobile-meta">
                                        <span>{{ $formattedTotal }}</span>
                                        <span @class([
                                            'ag-badge',
                                            'ag-badge--success' => $order->status->value === 'paid',
                                            'ag-badge--warning' => $order->status->value === 'pending',
                                            'ag-badge--muted' => $order->status->value === 'cancelled',
                                        ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="ag-table__primary">
                                    <span>{{ $order->customer_name }}</span>
                                    <span class="ag-muted">{{ $order->customer_email }}</span>
                                </div>
                            </td>
                            <td>{{ $formattedTotal }}</td>
                            <td>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $order->status->value === 'paid',
                                    'ag-badge--warning' => $order->status->value === 'pending',
                                    'ag-badge--muted' => $order->status->value === 'cancelled',
                                ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                            </td>
                            <td class="ag-table__col--md">
                                @if ($order->payment)
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => $order->payment->status->value === 'cancelled',
                                    ])>{{ __('admin.orders.payment_status.'.$order->payment->status->value) }}</span>
                                @else
                                    <span class="ag-muted">{{ __('common.em_dash') }}</span>
                                @endif
                            </td>
                            <td class="ag-table__col--lg">
                                <span class="ag-muted" title="{{ $order->created_at?->toDateTimeString() }}">
                                    {{ $order->created_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <x-ag.row-actions>
                                    @can('orders.update')
                                        <a
                                            class="ag-icon-btn"
                                            href="{{ route('admin.orders.edit', $order) }}"
                                            title="{{ __('admin.orders.actions.edit') }}"
                                            aria-label="{{ __('admin.orders.actions.edit_aria', ['number' => $order->number]) }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                    @endcan
                                    <div class="ag-menu" x-data="agDisclosure" @keydown.escape.window="close()" @click.outside="close()">
                                        <button type="button" class="ag-icon-btn" @click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu" title="{{ __('common.actions') }}" aria-label="{{ __('common.actions') }}: {{ $order->number }}">
                                            <x-ag.icon name="more-horizontal" :size="16" />
                                        </button>
                                        <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                            <a class="ag-menu__item" role="menuitem" href="{{ route('admin.orders.show', $order) }}">{{ __('admin.orders.open') }}</a>
                                            @can('orders.update')
                                                <a class="ag-menu__item" role="menuitem" href="{{ route('admin.orders.edit', $order) }}">{{ __('admin.orders.actions.edit') }}</a>
                                            @endcan
                                            @can('orders.delete')
                                                <button type="button" class="ag-menu__item ag-menu__item--danger" role="menuitem" wire:click="confirmDelete({{ $order->id }})">{{ __('admin.orders.actions.delete') }}</button>
                                            @endcan
                                        </div>
                                    </div>
                                </x-ag.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @can('orders.delete')
            <div class="ag-menu ag-bulk-selection__actions ag-bulk-selection__floating-actions" x-data="agDisclosure" @keydown.escape.window="close(true)" @click.outside="close()">
                <button type="button" class="ag-icon-btn" @click="toggle()" :aria-expanded="open.toString()" aria-controls="order-bulk-actions" title="{{ __('common.actions') }}" aria-label="{{ __('common.actions') }}" @disabled(count($selectedOrderIds) === 0) :disabled="isEmpty"><x-ag.icon name="more-horizontal" :size="16" /></button>
                <div id="order-bulk-actions" class="ag-menu__panel" x-show="open" x-cloak role="group" aria-label="{{ __('common.actions') }}">
                    <button type="button" class="ag-menu__item ag-menu__item--danger" wire:click="bulkDeleteOrders" wire:confirm="{{ __('admin.orders.bulk_confirm_delete') }}" :disabled="isEmpty">{{ __('admin.orders.bulk_delete') }}</button>
                </div>
            </div>
        @endcan
        </div>
        <div class="ag-pagination">{{ $orders->links() }}</div>
    @endif

    @if ($confirmingOrder)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-order-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h2 id="delete-order-title" class="ag-modal__title">{{ __('admin.orders.delete.title', ['number' => $confirmingOrder->number]) }}</h2>
                <p class="ag-modal__text">{{ __('admin.orders.delete.text') }}</p>
                <div class="ag-modal__actions">
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteOrder">{{ __('admin.orders.actions.delete') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>

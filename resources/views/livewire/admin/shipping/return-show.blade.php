<div class="admin-page c-returns c-returns--detail">
    <x-ag.page-header
        :heading="__('shipping::returns.admin_show_title', ['number' => $request->id])"
        :lede="__('shipping::returns.money_hint')"
    >
        <x-slot:actions>
            <a class="ag-btn ag-btn--ghost" href="{{ route('admin.shipping.returns') }}">{{ __('shipping::returns.back_to_index') }}</a>
            @if ($request->order)
                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.orders.show', $request->order) }}">{{ __('shipping::returns.open_order') }}</a>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <section class="ag-section c-returns__summary" aria-label="{{ __('shipping::returns.admin_show_title', ['number' => $request->id]) }}">
        <div class="ag-section__body c-returns__summary-grid">
            <div class="c-returns__summary-status">
                <x-ag.icon-tile name="rotate-ccw" tone="blue" />
                <div>
                    <span class="c-returns__label">{{ __('shipping::returns.status') }}</span>
                    <x-ag.badge :variant="match ($request->status->value) {
                        'approved', 'completed' => 'success',
                        'requested' => 'info',
                        'received' => 'warning',
                        'rejected' => 'danger',
                        default => 'muted',
                    }">{{ __('shipping::returns.statuses.'.$request->status->value) }}</x-ag.badge>
                </div>
            </div>
            <div class="c-returns__summary-field">
                <span class="c-returns__label">{{ __('shipping::returns.order') }}</span>
                <strong>{{ $request->order?->number ?? __('common.em_dash') }}</strong>
            </div>
            <div class="c-returns__summary-field">
                <span class="c-returns__label">{{ __('shipping::returns.customer') }}</span>
                <span>{{ $request->customer_email }}</span>
            </div>
            <div class="c-returns__summary-field c-returns__summary-reason">
                <span class="c-returns__label">{{ __('shipping::returns.reason') }}</span>
                <span>{{ $request->reason ?? __('shipping::returns.no_reason') }}</span>
            </div>
        </div>
    </section>

    <section class="ag-section c-returns__items">
        <header class="ag-section__header c-returns__heading">
            <x-ag.icon-tile name="package" tone="violet" />
            <h2 class="ag-section__title">{{ __('shipping::returns.items') }}</h2>
        </header>
        <div class="ag-table-wrap c-returns__items-wrap" role="region" aria-label="{{ __('shipping::returns.items') }}" tabindex="0">
            <table class="ag-table c-returns__items-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col">{{ __('shipping::returns.requested_quantity') }}</th>
                        <th scope="col">{{ __('shipping::returns.restocked_quantity') }}</th>
                        @can('returns.manage')
                            <th scope="col">{{ __('shipping::returns.restock_quantity') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($request->items as $item)
                        <tr wire:key="return-item-{{ $item->id }}">
                            <td class="c-returns__item-name" data-label="{{ __('common.product') }}">{{ $item->orderItem?->label ?? __('common.em_dash') }}</td>
                            <td class="c-returns__item-detail" data-label="{{ __('shipping::returns.requested_quantity') }}">{{ $item->quantity }}</td>
                            <td class="c-returns__item-detail" data-label="{{ __('shipping::returns.restocked_quantity') }}">{{ $item->restocked_quantity }}</td>
                            @can('returns.manage')
                                <td class="c-returns__item-detail" data-label="{{ __('shipping::returns.restock_quantity') }}">
                                    @if ($canRestock && $item->restockableQuantity() > 0)
                                        <input
                                            class="ag-input c-returns__restock-input"
                                            type="number"
                                            min="0"
                                            max="{{ $item->restockableQuantity() }}"
                                            aria-label="{{ __('shipping::returns.restock_quantity') }}: {{ $item->orderItem?->label ?? __('common.em_dash') }}"
                                            wire:model="restock_quantities.{{ $item->id }}"
                                        >
                                    @else
                                        <span class="ag-muted">{{ __('common.em_dash') }}</span>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @can('returns.manage')
        @if ($canApprove || $canReceive || $canComplete || $canReject)
            <section class="ag-section c-returns__workflow">
                <header class="ag-section__header c-returns__heading">
                    <x-ag.icon-tile name="check" tone="emerald" />
                    <h2 class="ag-section__title">{{ __('shipping::returns.status') }}</h2>
                </header>
                <div class="ag-section__body">
                    <div class="ag-toolbar c-returns__workflow-actions">
                        @if ($canApprove)
                            <button type="button" class="ag-btn ag-btn--primary" wire:click="approve">{{ __('shipping::returns.approve') }}</button>
                        @endif
                        @if ($canReceive)
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="markReceived">{{ __('shipping::returns.mark_received') }}</button>
                        @endif
                        @if ($canComplete)
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="complete">{{ __('shipping::returns.mark_completed') }}</button>
                        @endif
                    </div>

                    @if ($canReject)
                        <form wire:submit="reject" class="ag-form c-returns__reject-form">
                            <div class="ag-field">
                                <label class="ag-field__label" for="reject-reason">{{ __('shipping::returns.reject_reason') }}</label>
                                <input id="reject-reason" class="ag-input" type="text" wire:model="reject_reason" required>
                            </div>
                            <button type="submit" class="ag-btn ag-btn--danger">{{ __('shipping::returns.reject') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        @if ($canRestock)
            <section class="ag-section c-returns__restock">
                <header class="ag-section__header c-returns__heading">
                    <x-ag.icon-tile name="warehouse" tone="amber" />
                    <div>
                        <h2 class="ag-section__title">{{ __('shipping::returns.restock_title') }}</h2>
                        <p class="ag-section__lede">{{ __('shipping::returns.restock_hint') }}</p>
                    </div>
                </header>
                <div class="ag-section__body">
                    @unless ($inventoryAvailable)
                        <p class="ag-alert ag-alert--warning" role="status">{{ __('shipping::returns.restock_unavailable') }}</p>
                    @endunless
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="restock" @disabled(! $inventoryAvailable)>
                        {{ __('shipping::returns.restock') }}
                    </button>
                </div>
            </section>
        @endif

        <form wire:submit="saveNotes" class="ag-form ag-section c-returns__notes">
            <header class="ag-section__header c-returns__heading">
                <x-ag.icon-tile name="file-text" tone="blue" />
                <div>
                    <h2 class="ag-section__title">{{ __('shipping::returns.staff_notes') }}</h2>
                    <p class="ag-section__lede">{{ __('shipping::returns.staff_notes_hint') }}</p>
                </div>
            </header>
            <div class="ag-section__body">
                <div class="ag-field">
                    <label class="ag-field__label" for="staff-notes">{{ __('shipping::returns.staff_notes') }}</label>
                    <textarea id="staff-notes" class="ag-input" rows="4" wire:model="staff_notes"></textarea>
                </div>
                <div class="ag-form__actions c-returns__form-actions">
                    <button type="submit" class="ag-btn ag-btn--secondary">{{ __('shipping::returns.save_notes') }}</button>
                </div>
            </div>
        </form>
    @endcan
</div>

<div class="admin-page">
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

    <section class="ag-section" style="margin-bottom: 1.5rem;">
        <div class="ag-section__body">
            <p>
                <strong>{{ __('shipping::returns.status') }}:</strong>
                {{ __('shipping::returns.statuses.'.$request->status->value) }}
            </p>
            <p>
                <strong>{{ __('shipping::returns.order') }}:</strong>
                {{ $request->order?->number ?? __('common.em_dash') }}
                ·
                <strong>{{ __('shipping::returns.customer') }}:</strong>
                {{ $request->customer_email }}
            </p>
            <p>
                <strong>{{ __('shipping::returns.reason') }}:</strong>
                {{ $request->reason ?? __('shipping::returns.no_reason') }}
            </p>
        </div>
    </section>

    <section class="ag-section" style="margin-bottom: 1.5rem;">
        <header class="ag-section__header">
            <h3 class="ag-section__title">{{ __('shipping::returns.items') }}</h3>
        </header>
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('common.product') }}</th>
                        <th>{{ __('shipping::returns.requested_quantity') }}</th>
                        <th>{{ __('shipping::returns.restocked_quantity') }}</th>
                        @can('returns.manage')
                            <th>{{ __('shipping::returns.restock_quantity') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($request->items as $item)
                        <tr wire:key="return-item-{{ $item->id }}">
                            <td>{{ $item->orderItem?->label ?? __('common.em_dash') }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->restocked_quantity }}</td>
                            @can('returns.manage')
                                <td>
                                    @if ($canRestock && $item->restockableQuantity() > 0)
                                        <input
                                            class="ag-input"
                                            type="number"
                                            min="0"
                                            max="{{ $item->restockableQuantity() }}"
                                            style="max-width: 6rem;"
                                            aria-label="{{ __('shipping::returns.restock_quantity') }}"
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
        <section class="ag-section" style="margin-bottom: 1.5rem;">
            <div class="ag-section__body">
                <div class="ag-toolbar">
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
                    <form wire:submit="reject" class="ag-form" style="margin-top: 1rem;">
                        <div class="ag-field">
                            <label class="ag-field__label" for="reject-reason">{{ __('shipping::returns.reject_reason') }}</label>
                            <input id="reject-reason" class="ag-input" type="text" wire:model="reject_reason" required>
                        </div>
                        <button type="submit" class="ag-btn ag-btn--danger">{{ __('shipping::returns.reject') }}</button>
                    </form>
                @endif
            </div>
        </section>

        @if ($canRestock)
            <section class="ag-section" style="margin-bottom: 1.5rem;">
                <header class="ag-section__header">
                    <h3 class="ag-section__title">{{ __('shipping::returns.restock_title') }}</h3>
                    <p class="ag-section__lede">{{ __('shipping::returns.restock_hint') }}</p>
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

        <form wire:submit="saveNotes" class="ag-form ag-section">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('shipping::returns.staff_notes') }}</h3>
                <p class="ag-section__lede">{{ __('shipping::returns.staff_notes_hint') }}</p>
            </header>
            <div class="ag-section__body">
                <div class="ag-field">
                    <label class="ag-field__label" for="staff-notes">{{ __('shipping::returns.staff_notes') }}</label>
                    <textarea id="staff-notes" class="ag-input" rows="4" wire:model="staff_notes"></textarea>
                </div>
            </div>
            <button type="submit" class="ag-btn ag-btn--secondary">{{ __('shipping::returns.save_notes') }}</button>
        </form>
    @endcan
</div>

<div class="admin-page">
    <x-ag.page-header :heading="__('admin.invoices.title')" :lede="__('admin.invoices.lede')" />

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="invoice-search">{{ __('admin.invoices.search_label') }}</label>
                <input id="invoice-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.invoices.search_placeholder') }}">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="invoice-status">{{ __('admin.invoices.status_label') }}</label>
                <select id="invoice-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.invoices.status_all') }}</option>
                    @foreach ($statuses as $case)
                        <option value="{{ $case->value }}">{{ __('admin.invoices.status.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @if ($invoices->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.invoices.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.invoices.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table ag-table--invoices">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.invoices.number') }}</th>
                        <th scope="col">{{ __('common.customer') }}</th>
                        <th scope="col">{{ __('common.total') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col">{{ __('admin.invoices.issued') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->id }}">
                            <td>
                                <a class="ag-table__name" href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                            </td>
                            <td>
                                <div class="ag-table__primary">
                                    <span>{{ $invoice->customer_name }}</span>
                                    <span class="ag-muted">{{ $invoice->customer_email }}</span>
                                </div>
                            </td>
                            <td>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
                            <td>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $invoice->status->value === 'paid',
                                    'ag-badge--info' => $invoice->status->value === 'issued',
                                    'ag-badge--danger' => $invoice->status->value === 'void',
                                ])>{{ __('admin.invoices.status.'.$invoice->status->value) }}</span>
                            </td>
                            <td>
                                <span class="ag-muted" title="{{ $invoice->issued_at?->toDateTimeString() }}">
                                    {{ $invoice->issued_at?->format('Y-m-d') }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('invoices.update')
                                        <a
                                            class="ag-icon-btn"
                                            href="{{ route('admin.invoices.edit', $invoice) }}"
                                            title="{{ __('admin.invoices.edit_action') }}"
                                            aria-label="{{ __('admin.invoices.edit_action') }} {{ $invoice->number }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                    @endcan
                                    <a
                                        class="ag-icon-btn"
                                        href="{{ route('admin.invoices.pdf', $invoice) }}"
                                        title="{{ __('admin.invoices.download_pdf') }}"
                                        aria-label="{{ __('admin.invoices.download_pdf') }}"
                                    >
                                        <x-ag.icon name="download" :size="16" />
                                    </a>
                                    @can('invoices.delete')
                                        <button
                                            type="button"
                                            class="ag-icon-btn ag-icon-btn--danger"
                                            wire:click="confirmDelete({{ $invoice->id }})"
                                            title="{{ __('admin.invoices.delete_action') }}"
                                            aria-label="{{ __('admin.invoices.delete_action') }} {{ $invoice->number }}"
                                        >
                                            <x-ag.icon name="trash" :size="16" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $invoices->links() }}</div>
    @endif

    @if ($confirmingInvoice)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-invoice-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h3 id="delete-invoice-title" class="ag-modal__title">{{ __('admin.invoices.delete_confirm_title') }}</h3>
                <p class="ag-modal__text">
                    {{ __('admin.invoices.delete_confirm_text') }}
                    <br><strong>{{ $confirmingInvoice->number }}</strong>
                </p>
                <div class="ag-modal__actions">
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteInvoice">{{ __('common.confirm') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>

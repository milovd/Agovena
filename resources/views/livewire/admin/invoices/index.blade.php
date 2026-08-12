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

    @if ($invoices->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.invoices.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.invoices.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
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
                            <td><span class="ag-table__name">{{ $invoice->number }}</span></td>
                            <td>
                                <div class="ag-table__primary">
                                    <span>{{ $invoice->customer_name }}</span>
                                    <span class="ag-muted">{{ $invoice->customer_email }}</span>
                                </div>
                            </td>
                            <td>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
                            <td>{{ __('admin.invoices.status.'.$invoice->status->value) }}</td>
                            <td>{{ $invoice->issued_at?->format('Y-m-d') }}</td>
                            <td>
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.invoices.show', $invoice) }}">{{ __('common.view') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $invoices->links() }}</div>
    @endif
</div>

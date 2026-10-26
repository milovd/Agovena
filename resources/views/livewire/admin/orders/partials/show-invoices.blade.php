{{-- Order detail: linked invoices and credit notes, newest first. Part of livewire.admin.orders.show. --}}
@php
    $orderInvoices = $order->invoices->sortByDesc(static fn ($invoice): array => [$invoice->created_at?->getTimestamp() ?? 0, $invoice->id])->values();
    $invoiceBadge = static fn ($status): string => match ($status->value) {
        'paid' => 'success',
        'issued' => 'info',
        default => 'muted',
    };
@endphp
<section class="ag-card admin-order__invoices" aria-labelledby="order-invoices-heading">
    <header class="ag-card__header">
        <x-ag.icon-tile name="file-text" tone="amber" />
        <h2 id="order-invoices-heading" class="ag-card__title">{{ __('admin.orders.show.invoices') }}</h2>
    </header>
    <div class="ag-card__content">
        @if ($orderInvoices->isNotEmpty() || $order->creditNotes->isNotEmpty())
            <div class="ag-table-wrap" role="region" aria-label="{{ __('admin.orders.show.invoices') }}" tabindex="0">
                <table class="ag-table admin-order__invoice-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.invoices.number') }}</th>
                            <th scope="col">{{ __('common.status') }}</th>
                            <th scope="col">{{ __('admin.orders.show.issued') }}</th>
                            <th scope="col">{{ __('admin.orders.show.due') }}</th>
                            <th scope="col" class="admin-order__invoice-amount">{{ __('common.total') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('admin.orders.actions.more') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orderInvoices as $invoice)
                            <tr wire:key="order-invoice-{{ $invoice->id }}" @class(['admin-order__invoice--void' => $invoice->status->value === 'void'])>
                                <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                <td><span class="ag-badge ag-badge--{{ $invoiceBadge($invoice->status) }}">{{ __('admin.invoices.status.'.$invoice->status->value) }}</span></td>
                                <td>{{ $invoice->issued_at?->format('Y-m-d') ?? __('common.em_dash') }}</td>
                                <td>{{ $invoice->due_at?->format('Y-m-d') ?? __('common.em_dash') }}</td>
                                <td class="admin-order__invoice-amount">{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
                                <td class="admin-order__invoice-actions">
                                    <a class="ag-icon-btn" href="{{ route('admin.invoices.show', $invoice) }}" title="{{ __('admin.orders.show.view_invoice', ['number' => $invoice->number]) }}" aria-label="{{ __('admin.orders.show.view_invoice', ['number' => $invoice->number]) }}">
                                        <x-ag.icon name="eye" :size="16" />
                                    </a>
                                    @if ($canManageInvoices && $invoice->canChangeOrderAssociation())
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="unlinkInvoice({{ $invoice->id }})" wire:confirm="{{ __('admin.orders.show.invoice_unlink_confirm') }}" wire:loading.attr="disabled" wire:target="unlinkInvoice({{ $invoice->id }})" title="{{ __('admin.orders.inline.unlink_invoice', ['number' => $invoice->number]) }}" aria-label="{{ __('admin.orders.inline.unlink_invoice', ['number' => $invoice->number]) }}">
                                            <x-ag.icon name="link-2-off" :size="16" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($order->creditNotes as $note)
                            <tr wire:key="order-credit-note-{{ $note->id }}">
                                <td><a href="{{ route('admin.credit-notes.show', $note) }}">{{ $note->number }}</a></td>
                                <td><span class="ag-badge ag-badge--warning">{{ __('admin.orders.show.credit_note') }}</span></td>
                                <td>{{ $note->issued_at?->format('Y-m-d') ?? __('common.em_dash') }}</td>
                                <td>{{ __('common.em_dash') }}</td>
                                <td class="admin-order__invoice-amount">{{ \App\Support\MoneyFormatter::format(-1 * (int) $note->total_amount, $note->currency) }}</td>
                                <td class="admin-order__invoice-actions">
                                    <a class="ag-icon-btn" href="{{ route('admin.credit-notes.show', $note) }}" title="{{ $note->number }}" aria-label="{{ $note->number }}">
                                        <x-ag.icon name="eye" :size="16" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (! $canManageInvoices)
            <p class="ag-muted">{{ __('admin.orders.show.no_invoices') }}</p>
        @endif

        @if ($canManageInvoices)
            {{-- Type to find an unlinked invoice and add it with the plus. --}}
            <div class="admin-order__invoice-add" x-data="agSearchSelect" data-close-model="invoiceSearch" data-open-selector=".admin-order__invoice-results"
                @click.outside="close()" @keydown.escape.window="close()">
                <div class="ag-search-select__search admin-order__invoice-search">
                    <x-ag.icon name="plus" :size="15" />
                    <input id="invoice-search" type="search" wire:model.live.debounce.300ms="invoiceSearch" placeholder="{{ __('admin.orders.inline.add_invoice') }}" aria-label="{{ __('admin.orders.inline.add_invoice') }}" autocomplete="off">
                </div>
                @if (trim($invoiceSearch) !== '')
                    <div class="ag-search-select__panel admin-order__invoice-results" role="listbox">
                        @forelse ($invoiceCandidates as $candidate)
                            <button type="button" class="ag-search-select__option" role="option" wire:key="invoice-candidate-{{ $candidate->id }}" wire:click="linkInvoice({{ $candidate->id }})" aria-label="{{ __('admin.orders.inline.add_invoice_aria', ['number' => $candidate->number]) }}">
                                <span>{{ $candidate->number }} <span class="ag-muted">{{ $candidate->customer_name }}</span></span>
                                <span class="ag-search-select__hint">{{ \App\Support\MoneyFormatter::format($candidate->total_amount, $candidate->currency) }} <x-ag.icon name="plus" :size="14" /></span>
                            </button>
                        @empty
                            <p class="ag-search-select__empty">{{ __('admin.orders.inline.no_results') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>

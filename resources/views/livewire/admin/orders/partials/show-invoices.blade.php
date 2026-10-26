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
        <p class="ag-card__description">{{ __('admin.orders.show.invoices_lede') }}</p>
    </header>
    <div class="ag-card__content">
        @if ($orderInvoices->isEmpty() && $order->creditNotes->isEmpty())
            <p class="ag-muted">{{ __('admin.orders.show.no_invoices') }}</p>
        @else
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
                                        <button type="button" class="ag-icon-btn" wire:click="unlinkInvoice({{ $invoice->id }})" wire:confirm="{{ __('admin.orders.show.invoice_unlink_confirm') }}" wire:loading.attr="disabled" wire:target="unlinkInvoice({{ $invoice->id }})" title="{{ __('common.remove') }}" aria-label="{{ __('common.remove') }}">
                                            <x-ag.icon name="x" :size="16" />
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
        @endif

        @if ($canManageInvoices)
            <div class="ag-stack ag-u-mt-4">
                @if (! $showInvoiceLinker)
                    <div><button type="button" class="ag-btn ag-btn--secondary" wire:click="startLinkInvoice">{{ __('admin.orders.show.link_invoice') }}</button></div>
                @else
                    <div class="ag-stack" role="group" aria-labelledby="link-invoice-heading">
                        <h3 id="link-invoice-heading" class="ag-section__title">{{ __('admin.orders.show.link_invoice') }}</h3>
                        <label class="ag-field__label" for="invoice-search">{{ __('admin.invoices.search_label') }}</label>
                        <input id="invoice-search" class="ag-input" type="search" wire:model.live.debounce.300ms="invoiceSearch" placeholder="{{ __('admin.invoices.search_placeholder') }}" autocomplete="off">
                        <div class="ag-table-wrap" role="region" aria-label="{{ __('admin.orders.show.link_invoice') }}" tabindex="0">
                            <table class="ag-table">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('admin.invoices.number') }}</th>
                                        <th scope="col">{{ __('admin.invoices.customer') }}</th>
                                        <th scope="col"><span class="visually-hidden">{{ __('admin.orders.actions.more') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($invoiceCandidates as $candidate)
                                        <tr wire:key="invoice-candidate-{{ $candidate->id }}">
                                            <td>{{ $candidate->number }}</td>
                                            <td>{{ $candidate->customer_name }}<br><span class="ag-muted">{{ $candidate->customer_email }}</span></td>
                                            <td><button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="linkInvoice({{ $candidate->id }})" wire:loading.attr="disabled" wire:target="linkInvoice({{ $candidate->id }})">{{ __('admin.orders.show.link') }}</button></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="ag-muted">{{ __('admin.orders.show.no_invoice_candidates') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div><button type="button" class="ag-btn ag-btn--ghost" wire:click="cancelLinkInvoice">{{ __('common.cancel') }}</button></div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>

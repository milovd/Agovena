{{-- Order detail sidebar: invoices and credit notes. Part of livewire.admin.orders.show. --}}
@if ($order->invoices->isNotEmpty() || $order->creditNotes->isNotEmpty() || $canManageInvoices)
    <section class="ag-section" aria-labelledby="documents-heading">
        <header class="ag-section__header">
            <h3 id="documents-heading" class="ag-section__title">{{ __('admin.orders.show.documents') }}</h3>
        </header>
        <div class="ag-section__body">
            @if ($order->invoices->isNotEmpty())
                <div class="ag-table-wrap">
                    <table class="ag-table">
                        <caption class="visually-hidden">{{ __('admin.orders.show.documents') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.invoices.number') }}</th>
                                <th scope="col">{{ __('common.status') }}</th>
                                <th scope="col">{{ __('common.total') }}</th>
                                @if ($canManageInvoices)
                                    <th scope="col"><span class="visually-hidden">{{ __('admin.orders.actions.more') }}</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->invoices as $invoice)
                                <tr wire:key="order-invoice-{{ $invoice->id }}">
                                    <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                    <td>{{ __('admin.invoices.status.'.$invoice->status->value) }}</td>
                                    <td>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
                                    @if ($canManageInvoices)
                                        <td>
                                            @if ($invoice->canChangeOrderAssociation())
                                                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="unlinkInvoice({{ $invoice->id }})" wire:confirm="{{ __('admin.orders.show.invoice_unlink_confirm') }}" wire:loading.attr="disabled" wire:target="unlinkInvoice({{ $invoice->id }})">{{ __('common.remove') }}</button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($canManageInvoices)
                <div class="ag-stack" style="margin-top: var(--ag-space-4);">
                    @if (! $showInvoiceLinker)
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="startLinkInvoice">{{ __('admin.orders.show.link_invoice') }}</button>
                    @else
                        <div class="ag-stack" role="group" aria-labelledby="link-invoice-heading">
                            <h4 id="link-invoice-heading" class="ag-section__title">{{ __('admin.orders.show.link_invoice') }}</h4>
                            <label class="ag-field__label" for="invoice-search">{{ __('admin.invoices.search_label') }}</label>
                            <input id="invoice-search" class="ag-input" type="search" wire:model.live.debounce.300ms="invoiceSearch" placeholder="{{ __('admin.invoices.search_placeholder') }}" autocomplete="off">
                            <div class="ag-table-wrap">
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
                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="cancelLinkInvoice">{{ __('common.cancel') }}</button>
                        </div>
                    @endif
                </div>
            @endif

            @if ($order->creditNotes->isNotEmpty())
                <dl class="ag-dl" style="margin-top: var(--ag-space-4);">
                @foreach ($order->creditNotes as $note)
                    <div>
                        <dt>{{ __('admin.credit_notes.singular') }}</dt>
                        <dd><a href="{{ route('admin.credit-notes.show', $note) }}">{{ $note->number }}</a></dd>
                    </div>
                @endforeach
                </dl>
            @endif
        </div>
    </section>
@endif

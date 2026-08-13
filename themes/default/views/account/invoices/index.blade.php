<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.account.invoices_title') }}</h1>
        </header>

        @if ($invoices->isEmpty())
            <p class="store-account-panel__empty">{{ __('customer.account.no_invoices') }}</p>
        @else
            <div class="store-table-wrap">
                <table class="store-table">
                    <thead>
                        <tr>
                            <th>{{ __('customer.account.invoice_number') }}</th>
                            <th>{{ __('customer.account.order_date') }}</th>
                            <th>{{ __('customer.account.order_status') }}</th>
                            <th>{{ __('customer.account.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->number }}</td>
                                <td>{{ $invoice->issued_at?->format('Y-m-d') }}</td>
                                <td>{{ __('customer.account.invoice_statuses.'.$invoice->status->value) }}</td>
                                <td>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
                                <td><a href="{{ route('customer.invoices.show', $invoice) }}">{{ __('customer.account.view_invoice') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="store-account-panel__pagination">{{ $invoices->links() }}</div>
        @endif

        <h2 class="store-account-panel__title">{{ __('customer.account.credit_notes_title') }}</h2>
        @if ($creditNotes->isEmpty())
            <p class="store-account-panel__empty">{{ __('customer.account.no_credit_notes') }}</p>
        @else
            <div class="store-table-wrap">
                <table class="store-table">
                    <thead>
                        <tr>
                            <th>{{ __('customer.account.credit_note_number') }}</th>
                            <th>{{ __('customer.account.order_date') }}</th>
                            <th>{{ __('customer.account.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($creditNotes as $creditNote)
                            <tr>
                                <td>{{ $creditNote->number }}</td>
                                <td>{{ $creditNote->issued_at?->format('Y-m-d') }}</td>
                                <td>{{ \App\Support\MoneyFormatter::format($creditNote->total_amount, $creditNote->currency) }}</td>
                                <td><a href="{{ route('customer.credit-notes.show', $creditNote) }}">{{ __('customer.account.view_credit_note') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="store-account-panel__pagination">{{ $creditNotes->links() }}</div>
        @endif
    </section>
</div>

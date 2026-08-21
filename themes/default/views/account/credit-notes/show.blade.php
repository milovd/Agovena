<div class="store-account store-invoice">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            @include('theme::account.partials.breadcrumbs', [
                'back' => [
                    'url' => route('customer.invoices.index'),
                    'label' => __('customer.account.back_to_invoices'),
                ],
                'items' => [
                    ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                    ['label' => __('customer.account.invoices_title'), 'url' => route('customer.invoices.index')],
                    ['label' => $creditNote->number],
                ],
            ])
            <h1 class="store-account-panel__title">{{ $creditNote->number }}</h1>
            <p class="store-account-panel__lede">
                {{ __('customer.account.credit_note_issued') }}
                ·
                {{ $creditNote->issued_at?->format('Y-m-d') }}
            </p>
            <div class="store-account-panel__actions store-invoice__print">
                <a class="store-btn store-btn--secondary" href="{{ route('customer.credit-notes.print', $creditNote) }}">
                    {{ __('customer.account.print_credit_note') }}
                </a>
                <a class="store-btn store-btn--primary" href="{{ route('customer.credit-notes.pdf', $creditNote) }}">
                    {{ __('customer.account.download_credit_note_pdf') }}
                </a>
            </div>
        </header>

        <div class="store-account-panel__grid">
            <div>
                <h2>{{ __('customer.account.items') }}</h2>
                <ul class="store-order-items" role="list">
                    @foreach ($creditNote->items as $item)
                        <li class="store-order-items__row">
                            <div>
                                <strong>{{ $item->label }}</strong>
                                <p>{{ __('customer.account.quantity', ['count' => $item->quantity]) }}</p>
                            </div>
                            <strong>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</strong>
                        </li>
                    @endforeach
                </ul>
                <p class="store-order-items__total">
                    <span>{{ __('common.tax') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($creditNote->tax_amount, $creditNote->currency) }}</strong>
                </p>
                <p class="store-order-items__total">
                    <span>{{ __('customer.account.total') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($creditNote->total_amount, $creditNote->currency) }}</strong>
                </p>
            </div>
            <div>
                <h2>{{ __('customer.account.billing') }}</h2>
                <p>{{ $creditNote->billing_name ?: $creditNote->customer_name }}</p>
                @if ($creditNote->billing_line1)
                    <p>{{ $creditNote->billing_line1 }}</p>
                    <p>{{ $creditNote->billing_postal_code }} {{ $creditNote->billing_city }}</p>
                    <p>{{ $creditNote->billing_country }}</p>
                @endif
                <p>{{ $creditNote->customer_email }}</p>
                <p>{{ __('customer.account.reason') }}: {{ $creditNote->reason }}</p>
                @if ($creditNote->invoice)
                    <p>
                        <a href="{{ route('customer.invoices.show', $creditNote->invoice) }}">{{ $creditNote->invoice->number }}</a>
                    </p>
                @endif
            </div>
        </div>
    </section>
</div>

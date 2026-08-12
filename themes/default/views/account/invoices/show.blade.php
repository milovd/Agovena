<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.invoices.index') }}">{{ __('customer.account.back_to_invoices') }}</a>
            </p>
            <h1 class="store-account-panel__title">{{ $invoice->number }}</h1>
            <p class="store-account-panel__lede">
                {{ __('customer.account.invoice_statuses.'.$invoice->status->value) }}
                ·
                {{ $invoice->issued_at?->format('Y-m-d') }}
            </p>
        </header>

        <div class="store-account-panel__grid">
            <div>
                <h2>{{ __('customer.account.items') }}</h2>
                <ul class="store-order-items" role="list">
                    @foreach ($invoice->items as $item)
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
                    <span>{{ __('customer.account.total') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</strong>
                </p>
            </div>
            <div>
                <h2>{{ __('customer.account.billing') }}</h2>
                <p>{{ $invoice->billing_name ?: $invoice->customer_name }}</p>
                @if ($invoice->billing_line1)
                    <p>{{ $invoice->billing_line1 }}</p>
                    <p>{{ $invoice->billing_postal_code }} {{ $invoice->billing_city }}</p>
                    <p>{{ $invoice->billing_country }}</p>
                @endif
                <p>{{ $invoice->customer_email }}</p>
            </div>
        </div>
    </section>
</div>

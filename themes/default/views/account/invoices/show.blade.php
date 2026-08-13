<div class="store-account store-invoice">
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
            <div class="store-account-panel__actions store-invoice__print">
                <a class="store-btn store-btn--secondary" href="{{ route('customer.invoices.print', $invoice) }}">
                    {{ __('customer.account.print_invoice') }}
                </a>
                <a class="store-btn store-btn--primary" href="{{ route('customer.invoices.pdf', $invoice) }}">
                    {{ __('customer.account.download_invoice_pdf') }}
                </a>
            </div>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @error('pay_gateway')
            <p class="store-alert store-alert--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($invoice->order?->isAwaitingPayment())
            <form class="store-account-panel__section" wire:submit="payNow">
                <h2>{{ __('customer.account.pay_now') }}</h2>
                <p>{{ __('customer.account.amount_due', ['amount' => \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency)]) }}</p>
                <label>
                    {{ __('customer.account.payment_method') }}
                    <select wire:model="pay_gateway">
                        @foreach ($paymentOptions as $option)
                            <option value="{{ $option['id'] }}">{{ __($option['label']) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="store-btn store-btn--primary">{{ __('customer.account.pay_now') }}</button>
            </form>
        @endif

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
                    <span>{{ __('common.tax') }}</span>
                    <strong>{{ \App\Support\MoneyFormatter::format($invoice->tax_amount, $invoice->currency) }}</strong>
                </p>
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
                @foreach ($invoice->custom_properties_snapshot ?? [] as $property)
                    <p><strong>{{ $property['label'] ?? $property['key'] }}:</strong> {{ $property['value'] ?? '' }}</p>
                @endforeach

                @if ($invoice->order?->payment)
                    <h2>{{ __('customer.account.payment_heading') }}</h2>
                    <p>{{ __('customer.account.amount_paid', ['amount' => \App\Support\MoneyFormatter::format($invoice->order->payment->amount, $invoice->order->payment->currency)]) }}</p>
                    @if ($invoice->order->payment->refundedAmount() > 0)
                        <p>{{ __('customer.account.amount_refunded', ['amount' => \App\Support\MoneyFormatter::format($invoice->order->payment->refundedAmount(), $invoice->order->payment->currency)]) }}</p>
                        <p>{{ __('customer.account.net_paid', ['amount' => \App\Support\MoneyFormatter::format($invoice->order->payment->amount - $invoice->order->payment->refundedAmount(), $invoice->order->payment->currency)]) }}</p>
                    @endif
                @endif

                @if ($invoice->creditNotes->isNotEmpty())
                    <h2>{{ __('customer.account.credit_notes_title') }}</h2>
                    <ul>
                        @foreach ($invoice->creditNotes as $note)
                            <li>
                                <a href="{{ route('customer.credit-notes.show', $note) }}">{{ $note->number }}</a>
                                · {{ \App\Support\MoneyFormatter::format($note->total_amount, $note->currency) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</div>

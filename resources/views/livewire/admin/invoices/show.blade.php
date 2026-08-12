<div class="admin-page admin-invoice">
    <x-ag.page-header
        :heading="$invoice->number"
        :lede="__('admin.invoices.show_lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.invoices.index')" :label="__('admin.invoices.title')" />
        </x-slot:back>
        <x-slot:actions>
            <button type="button" class="ag-btn ag-btn--secondary admin-invoice__print" onclick="window.print()">
                {{ __('admin.invoices.print') }}
            </button>
        </x-slot:actions>
    </x-ag.page-header>

    <div class="ag-grid ag-grid--2">
        <section class="ag-section">
            <h2 class="ag-section__title">{{ __('admin.invoices.items') }}</h2>
            <ul class="ag-list">
                @foreach ($invoice->items as $item)
                    <li class="ag-list__row">
                        <div>
                            <strong>{{ $item->label }}</strong>
                            <p class="ag-muted">× {{ $item->quantity }}</p>
                        </div>
                        <strong>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</strong>
                    </li>
                @endforeach
            </ul>
            <dl class="ag-dl">
                <div><dt>{{ __('common.subtotal') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($invoice->subtotal_amount, $invoice->currency) }}</dd></div>
                @if ($invoice->discount_amount > 0)
                    <div><dt>{{ __('common.discount') }}</dt><dd>−{{ \App\Support\MoneyFormatter::format($invoice->discount_amount, $invoice->currency) }}</dd></div>
                @endif
                <div><dt>{{ __('common.tax') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($invoice->tax_amount, $invoice->currency) }}</dd></div>
            </dl>
            <p class="ag-total">
                <span>{{ __('common.total') }}</span>
                <strong>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</strong>
            </p>
        </section>

        <section class="ag-section">
            <h2 class="ag-section__title">{{ __('admin.invoices.details') }}</h2>
            <dl class="ag-dl">
                <div><dt>{{ __('common.status') }}</dt><dd>{{ __('admin.invoices.status.'.$invoice->status->value) }}</dd></div>
                <div><dt>{{ __('admin.invoices.issued') }}</dt><dd>{{ $invoice->issued_at?->format('Y-m-d') }}</dd></div>
                <div><dt>{{ __('common.customer') }}</dt><dd>{{ $invoice->customer_name }} · {{ $invoice->customer_email }}</dd></div>
                @if ($invoice->order)
                    <div>
                        <dt>{{ __('admin.invoices.order') }}</dt>
                        <dd><a href="{{ route('admin.orders.show', $invoice->order) }}">{{ $invoice->order->number }}</a></dd>
                    </div>
                @endif
                @if ($invoice->billing_line1)
                    <div>
                        <dt>{{ __('admin.invoices.billing') }}</dt>
                        <dd>
                            {{ $invoice->billing_name }}<br>
                            {{ $invoice->billing_line1 }}<br>
                            {{ $invoice->billing_postal_code }} {{ $invoice->billing_city }}<br>
                            {{ $invoice->billing_country }}
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>
</div>

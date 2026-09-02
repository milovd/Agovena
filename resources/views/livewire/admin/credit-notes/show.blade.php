<div class="admin-page admin-invoice">
    <x-ag.page-header
        :heading="$creditNote->number"
        :lede="__('admin.credit_notes.show_lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.invoices.show', $creditNote->invoice)" :label="$creditNote->invoice->number" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.credit-notes.print', $creditNote) }}">
                {{ __('admin.credit_notes.print') }}
            </a>
            <a class="ag-btn ag-btn--primary" href="{{ route('admin.credit-notes.pdf', $creditNote) }}">
                {{ __('admin.credit_notes.download_pdf') }}
            </a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-grid ag-grid--2">
        <section class="ag-section">
            <h2 class="ag-section__title">{{ __('admin.invoices.items') }}</h2>
            <ul class="ag-list">
                @foreach ($creditNote->items as $item)
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
                <div><dt>{{ __('common.subtotal') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($creditNote->subtotal_amount, $creditNote->currency) }}</dd></div>
                <div><dt>{{ __('common.tax') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($creditNote->tax_amount, $creditNote->currency) }}</dd></div>
            </dl>
            <p class="ag-total">
                <span>{{ __('common.total') }}</span>
                <strong>{{ \App\Support\MoneyFormatter::format($creditNote->total_amount, $creditNote->currency) }}</strong>
            </p>
        </section>

        <section class="ag-section">
            <h2 class="ag-section__title">{{ __('admin.invoices.details') }}</h2>
            <dl class="ag-dl">
                <div>
                    <dt>{{ __('common.status') }}</dt>
                    <dd><span class="ag-badge ag-badge--info">{{ __('admin.credit_notes.status.'.$creditNote->status->value) }}</span></dd>
                </div>
                <div><dt>{{ __('admin.invoices.issued') }}</dt><dd>{{ $creditNote->issued_at?->format('Y-m-d') }}</dd></div>
                <div>
                    <dt>{{ __('admin.invoices.number') }}</dt>
                    <dd><a href="{{ route('admin.invoices.show', $creditNote->invoice) }}">{{ $creditNote->invoice->number }}</a></dd>
                </div>
                @if ($creditNote->order)
                    <div>
                        <dt>{{ __('admin.invoices.order') }}</dt>
                        <dd><a href="{{ route('admin.orders.show', $creditNote->order) }}">{{ $creditNote->order->number }}</a></dd>
                    </div>
                @endif
                <div><dt>{{ __('admin.credit_notes.reason') }}</dt><dd>{{ $creditNote->reason }}</dd></div>
                <div><dt>{{ __('common.customer') }}</dt><dd>{{ $creditNote->customer_name }} · {{ $creditNote->customer_email }}</dd></div>
                @if ($creditNote->billing_line1)
                    <div>
                        <dt>{{ __('admin.invoices.billing') }}</dt>
                        <dd>
                            {{ $creditNote->billing_name }}<br>
                            @if ($creditNote->billing_company){{ $creditNote->billing_company }}<br>@endif
                            {{ $creditNote->billing_line1 }}<br>
                            @if ($creditNote->billing_line2){{ $creditNote->billing_line2 }}<br>@endif
                            {{ $creditNote->billing_postal_code }} {{ $creditNote->billing_city }}<br>
                            @if ($creditNote->billing_region){{ $creditNote->billing_region }}<br>@endif
                            {{ $creditNote->billing_country }}
                            @if ($creditNote->billing_phone)<br>{{ $creditNote->billing_phone }}@endif
                        </dd>
                    </div>
                @endif
                @foreach ($creditNote->custom_properties_snapshot ?? [] as $property)
                    <div>
                        <dt>{{ $property['label'] ?? $property['key'] }}</dt>
                        <dd>{{ $property['value'] ?? '' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>

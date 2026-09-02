<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 13px; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        .muted { color: #555; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; padding: 0 12px 0 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border-bottom: 1px solid #ddd; padding: 8px 0; text-align: left; }
        table.lines th.num, table.lines td.num { text-align: right; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .totals .total { font-weight: bold; font-size: 15px; }
        .options { margin: 0; padding-left: 16px; color: #555; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @if ($printable)
        <p class="no-print muted"><button type="button" onclick="window.print()">{{ __('invoices.print') }}</button></p>
    @endif

    <table class="grid">
        <tr>
            <td>
                <h1>{{ $invoice->number }}</h1>
                <p class="muted">{{ __('invoices.status.'.$invoice->status->value) }}</p>
                <p>{{ __('invoices.issued') }}: {{ $invoice->issued_at?->format('Y-m-d') }}</p>
                @if ($invoice->paid_at)
                    <p>{{ __('invoices.paid_on') }}: {{ $invoice->paid_at->format('Y-m-d') }}</p>
                @endif
            </td>
            <td>
                <h2>{{ __('invoices.seller') }}</h2>
                <p>{{ $invoice->merchant_name }}</p>
                @if ($invoice->merchant_address)
                    <p>{!! nl2br(e($invoice->merchant_address)) !!}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <h2>{{ __('invoices.bill_to') }}</h2>
                <p>{{ $invoice->billing_name ?: $invoice->customer_name }}</p>
                @if ($invoice->billing_company)
                    <p>{{ $invoice->billing_company }}</p>
                @endif
                @if ($invoice->billing_line1)
                    <p>{{ $invoice->billing_line1 }}</p>
                    @if ($invoice->billing_line2)
                        <p>{{ $invoice->billing_line2 }}</p>
                    @endif
                    <p>{{ $invoice->billing_postal_code }} {{ $invoice->billing_city }}</p>
                    @if ($invoice->billing_region)
                        <p>{{ $invoice->billing_region }}</p>
                    @endif
                    <p>{{ $invoice->billing_country }}</p>
                @endif
                @if ($invoice->billing_phone)
                    <p>{{ $invoice->billing_phone }}</p>
                @endif
                <p>{{ $invoice->customer_email }}</p>
                @foreach ($invoice->custom_properties_snapshot ?? [] as $property)
                    <p><strong>{{ $property['label'] ?? $property['key'] }}:</strong> {{ $property['value'] ?? '' }}</p>
                @endforeach
            </td>
            <td></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('invoices.item') }}</th>
                <th class="num">{{ __('invoices.qty') }}</th>
                <th class="num">{{ __('invoices.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                @php
                    $kind = $item->kind instanceof \App\Enums\InvoiceItemKind
                        ? $item->kind
                        : \App\Enums\InvoiceItemKind::Product;
                    $formatted = \App\Support\MoneyFormatter::format((int) $item->line_total_amount, $item->currency);
                @endphp
                <tr>
                    <td>
                        {{ $item->label }}
                        @if (is_array($item->options_snapshot) && $item->options_snapshot !== [])
                            <ul class="options">
                                @foreach ($item->options_snapshot as $option)
                                    <li>{{ $option['label'] ?? $option['key'] ?? '' }}: {{ $option['display'] ?? $option['value'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $kind->isAdjustment() ? '−'.$formatted : $formatted }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('common.subtotal') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($invoice->subtotal_amount, $invoice->currency) }}</td>
        </tr>
        @if ((int) $invoice->discount_amount > 0)
            <tr>
                <td>{{ __('common.discount') }}</td>
                <td class="num">−{{ \App\Support\MoneyFormatter::format((int) $invoice->discount_amount, $invoice->currency) }}</td>
            </tr>
        @endif
        @if ((int) $invoice->credit_amount > 0)
            <tr>
                <td>{{ __('common.credit') }}</td>
                <td class="num">−{{ \App\Support\MoneyFormatter::format((int) $invoice->credit_amount, $invoice->currency) }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ $invoice->tax_rate_name ?: __('common.tax') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($invoice->tax_amount, $invoice->currency) }}</td>
        </tr>
        <tr class="total">
            <td>{{ __('common.total') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</td>
        </tr>
    </table>
</body>
</html>

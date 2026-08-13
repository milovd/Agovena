<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $creditNote->number }}</title>
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
                <h1>{{ $creditNote->number }}</h1>
                <p class="muted">{{ __('credit_notes.document_title') }}</p>
                <p>{{ __('invoices.issued') }}: {{ $creditNote->issued_at?->format('Y-m-d') }}</p>
                @if ($creditNote->invoice)
                    <p>{{ __('credit_notes.related_invoice') }}: {{ $creditNote->invoice->number }}</p>
                @endif
            </td>
            <td>
                <h2>{{ __('invoices.seller') }}</h2>
                <p>{{ $creditNote->merchant_name }}</p>
                @if ($creditNote->merchant_address)
                    <p>{!! nl2br(e($creditNote->merchant_address)) !!}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <h2>{{ __('invoices.bill_to') }}</h2>
                <p>{{ $creditNote->billing_name ?: $creditNote->customer_name }}</p>
                @if ($creditNote->billing_company)
                    <p>{{ $creditNote->billing_company }}</p>
                @endif
                @if ($creditNote->billing_line1)
                    <p>{{ $creditNote->billing_line1 }}</p>
                    @if ($creditNote->billing_line2)
                        <p>{{ $creditNote->billing_line2 }}</p>
                    @endif
                    <p>{{ $creditNote->billing_postal_code }} {{ $creditNote->billing_city }}</p>
                    <p>{{ $creditNote->billing_country }}</p>
                @endif
                <p>{{ $creditNote->customer_email }}</p>
            </td>
            <td>
                <h2>{{ __('credit_notes.reason') }}</h2>
                <p>{{ $creditNote->reason }}</p>
            </td>
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
            @foreach ($creditNote->items as $item)
                @php
                    $kind = $item->kind instanceof \App\Enums\InvoiceItemKind
                        ? $item->kind
                        : \App\Enums\InvoiceItemKind::Product;
                    $formatted = \App\Support\MoneyFormatter::format((int) $item->line_total_amount, $item->currency);
                @endphp
                <tr>
                    <td>{{ $item->label }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $kind->isAdjustment() ? '−'.$formatted : $formatted }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('common.subtotal') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($creditNote->subtotal_amount, $creditNote->currency) }}</td>
        </tr>
        <tr>
            <td>{{ $creditNote->tax_rate_name ?: __('common.tax') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($creditNote->tax_amount, $creditNote->currency) }}</td>
        </tr>
        <tr class="total">
            <td>{{ __('common.total') }}</td>
            <td class="num">{{ \App\Support\MoneyFormatter::format($creditNote->total_amount, $creditNote->currency) }}</td>
        </tr>
    </table>
</body>
</html>

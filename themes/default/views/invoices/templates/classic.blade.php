<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
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
        @unless ($document->isCreditNote)
        .options { margin: 0; padding-left: 16px; color: #555; font-size: 12px; }
        @endunless
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
                <h1>{{ $document->number }}</h1>
                <p class="muted">{{ $document->statusLabel }}</p>
                <p>{{ __('invoices.issued') }}: {{ $document->issuedOn }}</p>
                @if ($document->paidOn)
                    <p>{{ __('invoices.paid_on') }}: {{ $document->paidOn }}</p>
                @endif
                @if ($document->relatedInvoiceNumber)
                    <p>{{ __('credit_notes.related_invoice') }}: {{ $document->relatedInvoiceNumber }}</p>
                @endif
            </td>
            <td>
                <h2>{{ __('invoices.seller') }}</h2>
                <p>{{ $document->sellerName }}</p>
                @if ($document->sellerAddress)
                    <p>{!! nl2br(e($document->sellerAddress)) !!}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <h2>{{ __('invoices.bill_to') }}</h2>
                <p>{{ $document->buyerName }}</p>
                @if ($document->buyerCompany)
                    <p>{{ $document->buyerCompany }}</p>
                @endif
                @if ($document->buyerLine1)
                    <p>{{ $document->buyerLine1 }}</p>
                    @if ($document->buyerLine2)
                        <p>{{ $document->buyerLine2 }}</p>
                    @endif
                    <p>{{ $document->buyerPostalCity }}</p>
                    @if ($document->buyerRegion)
                        <p>{{ $document->buyerRegion }}</p>
                    @endif
                    <p>{{ $document->buyerCountry }}</p>
                @endif
                @if ($document->buyerPhone)
                    <p>{{ $document->buyerPhone }}</p>
                @endif
                <p>{{ $document->buyerEmail }}</p>
                @foreach ($document->buyerProperties as $property)
                    <p><strong>{{ $property['label'] }}:</strong> {{ $property['value'] }}</p>
                @endforeach
            </td>
            @if ($document->isCreditNote)
                <td>
                    <h2>{{ __('credit_notes.reason') }}</h2>
                    <p>{{ $document->reason }}</p>
                </td>
            @else
                <td></td>
            @endif
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
            @foreach ($document->lines as $line)
                <tr>
                    <td>
                        {{ $line['label'] }}
                        @if ($line['options'] !== [])
                            <ul class="options">
                                @foreach ($line['options'] as $option)
                                    <li>{{ $option['label'] }}: {{ $option['value'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('common.subtotal') }}</td>
            <td class="num">{{ $document->subtotal }}</td>
        </tr>
        @if ($document->discount)
            <tr>
                <td>{{ __('common.discount') }}</td>
                <td class="num">{{ $document->discount }}</td>
            </tr>
        @endif
        @if ($document->credit)
            <tr>
                <td>{{ __('common.credit') }}</td>
                <td class="num">{{ $document->credit }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ $document->taxLabel }}</td>
            <td class="num">{{ $document->tax }}</td>
        </tr>
        <tr class="total">
            <td>{{ __('common.total') }}</td>
            <td class="num">{{ $document->total }}</td>
        </tr>
    </table>
</body>
</html>

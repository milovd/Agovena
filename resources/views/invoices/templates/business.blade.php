<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('invoices.partials.base-styles')
        .inv--business { font-size: 10px; }
        .inv--business .inv-head td { vertical-align: top; }
        .inv--business .inv-brand { font-size: 17px; font-weight: bold; color: {{ $design->accentText() }}; }
        .inv--business .inv-facts { width: 280px; margin-top: 14px; border: 1px solid {{ $design->tint(0.28) }}; }
        .inv--business .inv-facts td { padding: 4px 8px; border-bottom: 1px solid {{ $design->tint(0.14) }}; }
        .inv--business .inv-fact__label { width: 46%; background: {{ $design->tint(0.08) }}; color: #374151; }
        .inv--business .inv-fact__value { font-weight: bold; color: #111827; }
        .inv--business .inv-address { width: 44%; padding-top: 6px; }
        .inv--business .inv-address__label { margin-bottom: 6px; color: #6b7280; font-size: 8.5px; font-weight: bold; letter-spacing: 0.8px; text-transform: uppercase; }
        .inv--business .inv-label { color: #6b7280; }
        .inv--business .inv-panel { margin-top: 24px; border: 1px solid {{ $design->tint(0.28) }}; }
        .inv--business .inv-panel__title { padding: 6px 10px; background: {{ $design->tint(0.14) }}; color: {{ $design->accentText() }}; font-weight: bold; }
        .inv--business .inv-panel__body td { padding: 5px 10px; border-top: 1px solid {{ $design->tint(0.12) }}; }
        .inv--business .inv-panel__strong td { font-weight: bold; color: #111827; }
        .inv--business .inv-panel__status { width: 42%; padding: 8px 12px; border-left: 1px solid {{ $design->tint(0.28) }}; }
        .inv--business .inv-panel__status-label { margin-bottom: 3px; font-weight: bold; color: #111827; }
        .inv--business .inv-section-title { margin: 26px 0 8px; padding-bottom: 5px; border-bottom: 2px solid {{ $design->color }}; color: {{ $design->accentText() }}; font-size: 12.5px; }
        .inv--business .inv-lines { border: 1px solid #dfe3e8; }
        .inv--business .inv-lines th { padding: 6px 8px; background: {{ $design->tint(0.14) }}; color: {{ $design->accentText() }}; font-size: 9px; }
        .inv--business .inv-lines td { padding: 6px 8px; border-top: 1px solid #e5e7eb; }
        .inv--business .inv-summary { margin-top: 14px; }
        .inv--business .inv-summary__left { padding-right: 24px; }
        .inv--business .inv-summary__right { width: 280px; }
        .inv--business .inv-totals { border: 1px solid #dfe3e8; }
        .inv--business .inv-totals td { padding: 5px 10px; border-top: 1px solid #eef0f3; }
        .inv--business .inv-grand td { padding: 7px 10px; background: {{ $design->tint(0.14) }}; color: #111827; font-size: 11.5px; font-weight: bold; }
        .inv--business .inv-closing__block { margin-bottom: 12px; }
        .inv--business .inv-closing__heading { margin-bottom: 2px; color: {{ $design->accentText() }}; font-weight: bold; }
        .inv--business .inv-legal { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8.5px; text-align: center; }
        .inv--business .inv-legal p { margin-bottom: 1px; }
    </style>
</head>
<body class="is-{{ $mode }}">
    @include('invoices.partials.toolbar')
    <div class="inv-page inv--business">
        <table class="inv-head">
            <tr>
                <td>
                    @if ($design->shows('logo') && $design->logo)
                        <img class="inv-logo" src="{{ $design->logo }}" alt="{{ $document->sellerName }}">
                    @else
                        <p class="inv-brand">{{ $document->sellerName }}</p>
                    @endif
                    <table class="inv-facts">
                        <tr><td class="inv-fact__label">{{ $document->documentTitle }}</td><td class="inv-fact__value">{{ $document->number }}</td></tr>
                        @include('invoices.partials.facts', ['withNumber' => false, 'withStatus' => true])
                    </table>
                </td>
                <td class="inv-address">
                    <p class="inv-address__label">{{ __('invoices.bill_to') }}</p>
                    @include('invoices.partials.buyer')
                </td>
            </tr>
        </table>

        <table class="inv-panel">
            <tr>
                <td>
                    <p class="inv-panel__title">{{ __('invoices.summary_heading', ['document' => $document->documentTitle, 'number' => $document->number, 'date' => $document->issuedOn]) }}</p>
                    <table class="inv-panel__body">
                        <tr><td>{{ __('invoices.net_total') }}</td><td class="inv-num">{{ $document->netTotal }}</td></tr>
                        <tr><td>{{ $document->taxLabel }}</td><td class="inv-num">{{ $document->tax }}</td></tr>
                        <tr class="inv-panel__strong"><td>{{ __('invoices.gross_total') }}</td><td class="inv-num">{{ $document->total }}</td></tr>
                    </table>
                </td>
                <td class="inv-panel__status">
                    <p class="inv-panel__status-label">{{ __('invoices.payment_info') }}</p>
                    @if ($document->isCreditNote)
                        <p>{{ __('credit_notes.related_invoice') }}: {{ $document->relatedInvoiceNumber }}</p>
                    @elseif ($document->isPaid())
                        <p>{{ __('invoices.paid_message', ['date' => $document->paidOn]) }}</p>
                    @elseif ($document->dueOn)
                        <p>{{ __('invoices.due_message', ['date' => $document->dueOn]) }}</p>
                    @else
                        <p>{{ $document->statusLabel }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <h2 class="inv-section-title">{{ __('invoices.specification') }}</h2>
        @include('invoices.partials.lines')

        <table class="inv-summary">
            <tr>
                <td class="inv-summary__left">
                    @include('invoices.partials.closing')
                </td>
                <td class="inv-summary__right">
                    <table class="inv-totals">
                        @include('invoices.partials.totals')
                        <tr class="inv-grand"><td>{{ $totalLabel }}</td><td class="inv-num">{{ $document->total }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="inv-legal">
            <p class="inv-strong">{{ $document->sellerName }}</p>
            @php
                $legal = array_filter([
                    $design->shows('seller_address') && $document->sellerAddress ? str_replace("\n", ', ', trim($document->sellerAddress)) : null,
                    $design->shows('vat_number') && $document->sellerVatNumber ? __('invoices.seller_vat_number').': '.$document->sellerVatNumber : null,
                    $design->shows('company_number') && $document->sellerCompanyNumber ? __('invoices.seller_company_number').': '.$document->sellerCompanyNumber : null,
                ]);
                $contacts = array_map(static fn (array $line): string => $line['value'], $design->contactLines());
            @endphp
            @if ($legal !== [])
                <p>{{ implode(' | ', $legal) }}</p>
            @endif
            @if ($contacts !== [])
                <p>{{ implode(' | ', $contacts) }}</p>
            @endif
            @if ($design->footerText)
                <p>{{ $design->footerText }}</p>
            @endif
        </div>
    </div>
</body>
</html>

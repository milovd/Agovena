<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('invoices.partials.base-styles')
        .inv--clean { color: #1f2933; }
        .inv--clean .inv-title { font-size: 30px; font-weight: normal; letter-spacing: -0.5px; color: #111827; }
        .inv--clean .inv-subtitle { margin-top: 4px; font-size: 15px; color: #6b7280; }
        .inv--clean .inv-head__logo { width: 40%; text-align: right; }
        .inv--clean .inv-head__logo img { display: inline-block; }
        .inv--clean .inv-meta { margin-top: 28px; }
        .inv--clean .inv-meta td { width: 50%; }
        .inv--clean .inv-meta p { margin-bottom: 2px; }
        .inv--clean .inv-meta__right { text-align: right; }
        .inv--clean .inv-label { font-weight: bold; }
        .inv--clean .inv-buyer { margin-bottom: 12px; }
        .inv--clean .inv-buyer .inv-label { font-weight: normal; color: #6b7280; }
        .inv--clean .inv-rule { margin: 20px 0; border-top: 1px solid #e5e7eb; }
        .inv--clean .inv-section-title { margin-bottom: 14px; font-size: 15px; font-weight: normal; color: #111827; }
        .inv--clean .inv-lines th { padding: 0 0 10px; color: #6b7280; font-size: 9.5px; font-weight: normal; }
        .inv--clean .inv-lines td { padding: 9px 0; border-top: 1px solid #f1f2f4; }
        .inv--clean .inv-lines th + th, .inv--clean .inv-lines td + td { padding-left: 18px; }
        .inv--clean .inv-summary { margin-top: 16px; }
        .inv--clean .inv-summary__spacer { width: 56%; }
        .inv--clean .inv-totals td { padding: 3px 0; color: #4b5563; }
        .inv--clean .inv-due { padding-top: 14px; text-align: right; }
        .inv--clean .inv-due__label { font-weight: bold; }
        .inv--clean .inv-due__amount { margin-top: 4px; font-size: 22px; color: {{ $design->accentText() }}; }
        .inv--clean .inv-closing__block { margin-bottom: 14px; }
        .inv--clean .inv-closing__heading { font-weight: bold; }
        .inv--clean .inv-footer { margin-top: 26px; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body class="is-{{ $mode }}">
    @include('invoices.partials.toolbar')
    <div class="inv-page inv--clean">
        <table class="inv-head">
            <tr>
                <td>
                    <h1 class="inv-title">{{ $document->documentTitle }}</h1>
                    <p class="inv-subtitle">{{ $document->sellerName }}</p>
                </td>
                <td class="inv-head__logo">
                    @if ($design->shows('logo') && $design->logo)
                        <img class="inv-logo" src="{{ $design->logo }}" alt="{{ $document->sellerName }}">
                    @endif
                </td>
            </tr>
        </table>

        <table class="inv-meta">
            <tr>
                <td>
                    @include('invoices.partials.seller', ['labelled' => true])
                </td>
                <td class="inv-meta__right">
                    <div class="inv-buyer">
                        <p class="inv-label">{{ __('invoices.bill_to') }}:</p>
                        @include('invoices.partials.buyer')
                    </div>
                    <p><span class="inv-label">{{ $document->isCreditNote ? __('credit_notes.number') : __('invoices.number') }}:</span> {{ $document->number }}</p>
                    @if ($document->orderNumber)
                        <p><span class="inv-label">{{ __('invoices.order_number') }}:</span> {{ $document->orderNumber }}</p>
                    @endif
                    @foreach ($document->references() as $reference)
                        <p><span class="inv-label">{{ $reference['label'] }}:</span> {{ $reference['value'] }}</p>
                    @endforeach
                </td>
            </tr>
        </table>

        <div class="inv-rule"></div>

        <h2 class="inv-section-title">{{ __('invoices.specification') }}</h2>
        @include('invoices.partials.lines')

        <table class="inv-summary">
            <tr>
                <td class="inv-summary__spacer"></td>
                <td>
                    <table class="inv-totals">
                        @include('invoices.partials.totals')
                    </table>
                    <div class="inv-due">
                        <p class="inv-due__label">{{ $totalLabel }}:</p>
                        <p class="inv-due__amount">{{ $document->total }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <div class="inv-rule"></div>

        @include('invoices.partials.closing')

        @if ($design->footerText)
            <p class="inv-footer">{{ $design->footerText }}</p>
        @endif
    </div>
</body>
</html>

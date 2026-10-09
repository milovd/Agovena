<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('invoices.partials.base-styles', ['pageMargin' => $mode === 'pdf' ? '12mm 0 32mm 0' : null])
        .inv--bold { font-size: 11px; color: #1f1f1f; }
        .is-screen .inv--bold, .is-preview .inv--bold { padding-bottom: 46mm; }
        .inv--bold .inv-top td { vertical-align: top; font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; color: #1f1f1f; }
        .inv--bold .inv-top__number { text-align: right; }
        .inv--bold .inv-title { margin-top: 18px; font-size: 48px; font-weight: bold; line-height: 1; letter-spacing: -1px; text-transform: uppercase; color: #1c1c1c; }
        .inv--bold .inv-dates { margin-top: 12px; font-size: 11px; }
        .inv--bold .inv-dates p { margin-bottom: 2px; }
        .inv--bold .inv-label { font-weight: bold; }
        .inv--bold .inv-parties { margin-top: 16px; font-size: 10.5px; }
        .inv--bold .inv-parties td { width: 50%; padding-right: 24px; }
        .inv--bold .inv-parties__heading { margin-bottom: 4px; font-size: 12.5px; font-weight: bold; }
        .inv--bold .inv-parties .inv-label { font-weight: normal; color: #6b7280; }
        .inv--bold .inv-lines { margin-top: 18px; font-size: 10.5px; }
        .inv--bold .inv-lines th { padding: 9px 14px; background: {{ $design->tint(0.12) }}; font-weight: normal; font-size: 12px; color: #1c1c1c; }
        .inv--bold .inv-lines td { padding: 6px 14px; }
        .inv--bold .inv-summary { margin-top: 6px; }
        .inv--bold .inv-summary__spacer { width: 50%; }
        .inv--bold .inv-totals td { padding: 3px 14px; color: #4b5563; }
        .inv--bold .inv-grand td { padding: 11px 14px; border-top: 2px solid #e2e2e4; border-bottom: 2px solid #e2e2e4; font-size: 13px; font-weight: bold; color: #1c1c1c; }
        .inv--bold .inv-closing { margin-top: 14px; font-size: 10.5px; }
        .inv--bold .inv-closing__block { margin-bottom: 6px; }
        .inv--bold .inv-closing__heading { font-weight: bold; }
        .inv--bold .inv-footer { margin-top: 6px; color: #6b7280; font-size: 9px; }
        .inv--bold .inv-deco--bottom { left: 0; bottom: 0; width: 100%; height: 40mm; }
        .inv-bold-wave { position: fixed; left: 0; bottom: -32mm; width: 210mm; height: 32mm; }
    </style>
</head>
<body class="is-{{ $mode }}">
    @include('invoices.partials.toolbar')
    @if ($mode === 'pdf')
    <img class="inv-deco inv-deco--bottom inv-bold-wave" alt="" src="{{ $design->svg(
            '<path d="M0 46 C150 22 330 34 470 150 L470 190 L0 190 Z" fill="'.$design->tint(0.22).'"/>'
            .'<path d="M70 190 C210 128 390 112 560 120 C690 126 760 72 794 0 L794 190 Z" fill="'.$design->shade(0.25).'"/>',
            794,
            190,
        ) }}">
    @endif
    <div class="inv-page inv--bold">
    @unless ($mode === 'pdf')
        <img class="inv-deco inv-deco--bottom" alt="" src="{{ $design->svg(
            '<path d="M0 46 C150 22 330 34 470 150 L470 190 L0 190 Z" fill="'.$design->tint(0.22).'"/>'
            .'<path d="M70 190 C210 128 390 112 560 120 C690 126 760 72 794 0 L794 190 Z" fill="'.$design->shade(0.25).'"/>',
            794,
            190,
        ) }}">
    @endunless
        <table class="inv-top">
            <tr>
                <td>
                    @if ($design->shows('logo') && $design->logo)
                        <img class="inv-logo" src="{{ $design->logo }}" alt="{{ $document->sellerName }}">
                    @else
                        {{ $document->sellerName }}
                    @endif
                </td>
                <td class="inv-top__number">{{ __('invoices.number_short') }} {{ $document->number }}</td>
            </tr>
        </table>

        <h1 class="inv-title">{{ $document->documentTitle }}</h1>

        <div class="inv-dates">
            @if ($document->orderNumber)
                <p><span class="inv-label">{{ __('invoices.order_number') }}:</span> {{ $document->orderNumber }}</p>
            @endif
            @foreach ($document->references() as $reference)
                <p><span class="inv-label">{{ $reference['label'] }}:</span> {{ $reference['value'] }}</p>
            @endforeach
        </div>

        <table class="inv-parties">
            <tr>
                <td>
                    <p class="inv-parties__heading">{{ __('invoices.billed_to') }}:</p>
                    @include('invoices.partials.buyer')
                </td>
                <td>
                    <p class="inv-parties__heading">{{ __('invoices.seller') }}:</p>
                    <p>{{ $document->sellerName }}</p>
                    @include('invoices.partials.seller')
                </td>
            </tr>
        </table>

        @include('invoices.partials.lines')

        <table class="inv-summary">
            <tr>
                <td class="inv-summary__spacer"></td>
                <td>
                    <table class="inv-totals">
                        @include('invoices.partials.totals')
                        <tr class="inv-grand"><td>{{ $totalLabel }}</td><td class="inv-num">{{ $document->total }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="inv-closing">
            @include('invoices.partials.closing')
            @if ($design->footerText)
                <p class="inv-footer">{{ $design->footerText }}</p>
            @endif
        </div>

    </div>
</body>
</html>

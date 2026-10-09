<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('invoices.partials.base-styles', ['pageMargin' => $mode === 'pdf' ? '12mm 0 16mm 0' : null])
        .inv--banner { padding: 0 0 52px; font-size: 10.5px; color: #1e293b; }
        .is-screen .inv--banner, .is-preview .inv--banner { padding-top: 0; }
        .is-pdf .inv--banner { padding-top: 0; }
        .is-pdf .inv--banner .inv-band { margin-top: -16mm; padding-top: 44px; }
        .inv--banner .inv-band { position: relative; padding: 40px 58px 32px; background: {{ $design->color }}; color: {{ $design->ink() }}; overflow: hidden; }
        .inv--banner .inv-band__pattern { position: absolute; top: 0; right: 0; width: 360px; height: 100%; }
        .inv--banner .inv-band table { position: relative; }
        .inv--banner .inv-band td { vertical-align: top; }
        .inv--banner .inv-chip { display: inline-block; margin-bottom: 14px; padding: 6px 9px; border-radius: 6px; background: #ffffff; }
        .inv--banner .inv-chip img { display: block; max-width: 150px; max-height: 40px; }
        .inv--banner .inv-seller-name { margin-bottom: 4px; font-size: 16px; font-weight: bold; }
        .inv--banner .inv-band .inv-label { font-weight: bold; }
        .inv--banner .inv-band__seller { font-size: 9px; line-height: 1.5; }
        .inv--banner .inv-band__right { width: 46%; text-align: right; }
        .inv--banner .inv-title { font-size: 36px; font-weight: bold; line-height: 1; letter-spacing: 0.5px; text-transform: uppercase; }
        .inv--banner .inv-ref { margin-top: 6px; font-size: 11px; }
        .inv--banner .inv-band__facts { width: auto; margin-top: 16px; margin-left: auto; }
        .inv--banner .inv-band__facts td { padding: 1px 0 1px 18px; font-size: 9.5px; text-align: right; }
        .inv--banner .inv-band__facts .inv-fact__label { font-weight: bold; }
        .inv--banner .inv-body { padding: 0 58px; }
        .inv--banner .inv-parties { margin-top: 30px; }
        .inv--banner .inv-parties > tbody > tr > td { width: 50%; padding-right: 26px; }
        .inv--banner .inv-parties__heading { margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #94a3b8; color: {{ $design->accentText() }}; font-size: 12px; font-weight: bold; }
        .inv--banner .inv-parties .inv-label { color: #64748b; }
        .inv--banner .inv-details td { padding: 1px 0; }
        .inv--banner .inv-details .inv-fact__label { width: 45%; color: #64748b; }
        .inv--banner .inv-lines { margin-top: 28px; }
        .inv--banner .inv-lines th { padding: 8px 10px; background: {{ $design->color }}; color: {{ $design->ink() }}; font-size: 10px; }
        .inv--banner .inv-lines td { padding: 8px 10px; }
        .inv--banner .inv-lines tbody tr:nth-child(odd) td { background: {{ $design->tint(0.08) }}; }
        .inv--banner .inv-lines__no { width: 34px; text-align: center; }
        .inv--banner .inv-summary { margin-top: 10px; }
        .inv--banner .inv-summary__left { padding-right: 30px; }
        .inv--banner .inv-summary__right { width: 270px; }
        .inv--banner .inv-totals td { padding: 5px 10px; text-align: right; }
        .inv--banner .inv-grand td { padding: 8px 10px; font-weight: bold; color: #0f172a; }
        .inv--banner .inv-grand td.inv-num { background: {{ $design->tint(0.18) }}; font-size: 15px; }
        .inv--banner .inv-closing__block { margin-bottom: 14px; font-size: 9.5px; }
        .inv--banner .inv-closing__heading { margin-bottom: 3px; color: {{ $design->accentText() }}; font-size: 12px; font-weight: bold; }
        .inv--banner .inv-footer { margin-top: 30px; padding-top: 10px; border-top: 2px solid {{ $design->color }}; font-size: 9px; }
        .inv--banner .inv-footer td { vertical-align: top; }
        .inv--banner .inv-footer__contact { text-align: right; color: #475569; }
    </style>
</head>
<body class="is-{{ $mode }}">
    @include('invoices.partials.toolbar')
    @php
        $patternLine = $design->ink() === '#FFFFFF' ? $design->tint(0.7) : $design->shade(0.22);
        $pattern = '';
        for ($i = 0; $i < 11; $i++) {
            $x = 40 + ($i * 30);
            $pattern .= '<path d="M'.$x.' -10 C'.($x + 70).' 60 '.($x - 50).' 130 '.($x + 30).' 230" fill="none" stroke="'.$patternLine.'" stroke-width="2"/>';
        }
    @endphp
    <div class="inv-page inv--banner">
        <div class="inv-band">
            <img class="inv-deco inv-band__pattern" alt="" src="{{ $design->svg($pattern, 360, 220) }}">
            <table>
                <tr>
                    <td>
                        @if ($design->shows('logo') && $design->logo)
                            <span class="inv-chip"><img src="{{ $design->logo }}" alt="{{ $document->sellerName }}"></span>
                        @endif
                        <p class="inv-seller-name">{{ $document->sellerName }}</p>
                        <div class="inv-band__seller">
                            @include('invoices.partials.seller', ['labelled' => true, 'withContact' => false])
                        </div>
                    </td>
                    <td class="inv-band__right">
                        <h1 class="inv-title">{{ $document->documentTitle }}</h1>
                        <p class="inv-ref"><span class="inv-strong">{{ __('invoices.reference') }}</span> {{ $document->number }}</p>
                        <table class="inv-band__facts">
                            @include('invoices.partials.facts', ['withNumber' => false])
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="inv-body">
            <table class="inv-parties">
                <tr>
                    <td>
                        <p class="inv-parties__heading">{{ __('invoices.billed_to') }}:</p>
                        @include('invoices.partials.buyer')
                    </td>
                    <td>
                        <p class="inv-parties__heading">{{ __('invoices.details') }}:</p>
                        <table class="inv-details">
                            <tr><td class="inv-fact__label">{{ __('invoices.status_label') }}</td><td>{{ $document->statusLabel }}</td></tr>
                            @if ($document->orderNumber)
                                <tr><td class="inv-fact__label">{{ __('invoices.order_number') }}</td><td>{{ $document->orderNumber }}</td></tr>
                            @endif
                            @if ($document->relatedInvoiceNumber)
                                <tr><td class="inv-fact__label">{{ __('credit_notes.related_invoice') }}</td><td>{{ $document->relatedInvoiceNumber }}</td></tr>
                            @endif
                            <tr><td class="inv-fact__label">{{ __('invoices.currency') }}</td><td>{{ $document->currency }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            @include('invoices.partials.lines', ['numbered' => true])

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

            @php
                $contacts = $design->contactLines();
            @endphp
            @if ($design->footerText || $contacts !== [])
                <table class="inv-footer">
                    <tr>
                        <td class="inv-strong">{{ $design->footerText }}</td>
                        <td class="inv-footer__contact">
                            @foreach ($contacts as $contact)
                                <span class="inv-strong">{{ __('invoices.contact_'.$contact['key']) }}:</span> {{ $contact['value'] }}@unless ($loop->last) &nbsp;|&nbsp; @endunless
                            @endforeach
                        </td>
                    </tr>
                </table>
            @endif
        </div>
    </div>
</body>
</html>

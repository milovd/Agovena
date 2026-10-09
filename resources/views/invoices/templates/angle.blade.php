<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('invoices.partials.base-styles', ['pageMargin' => $mode === 'pdf' ? '36mm 0 32mm 0' : null])
        .inv--angle { font-size: 10.5px; color: #1f2933; }
        .is-screen .inv--angle, .is-preview .inv--angle { padding-top: 40mm; padding-bottom: 36mm; }
        .is-pdf .inv--angle { padding-top: 0; }
        .inv--angle .inv-top { position: absolute; top: 0; left: 0; width: 100%; height: 32mm; }
        .inv--angle .inv-top img.inv-deco { top: 0; left: 0; width: 100%; height: 32mm; }
        .inv--angle .inv-deco--bottom { left: 0; bottom: 0; width: 100%; height: 29mm; }
        .inv-angle-top { position: fixed; top: -36mm; left: 0; width: 210mm; height: 32mm; }
        .inv-angle-top img.inv-deco { position: absolute; top: 0; left: 0; width: 210mm; height: 32mm; }
        .inv-angle-top .inv-brand { position: absolute; top: 6mm; left: 58px; color: {{ $design->ink() }}; font-size: 19px; font-weight: bold; text-transform: uppercase; }
        .inv-angle-bottom { position: fixed; left: 0; bottom: -32mm; width: 210mm; height: 29mm; }
        .inv--angle .inv-brand { position: absolute; top: 6mm; left: 58px; color: {{ $design->ink() }}; font-size: 19px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .inv--angle .inv-chip, .inv-angle-top .inv-chip { display: inline-block; padding: 6px 9px; border-radius: 6px; background: #ffffff; }
        .inv--angle .inv-chip img, .inv-angle-top .inv-chip img { display: block; max-width: 150px; max-height: 38px; }
        .inv--angle .inv-title { font-size: 44px; font-weight: bold; line-height: 1.05; letter-spacing: 0.5px; text-transform: uppercase; color: {{ $design->accentText() }}; }
        .inv--angle .inv-intro { margin-top: 14px; }
        .inv--angle .inv-intro > tbody > tr > td { vertical-align: top; }
        .inv--angle .inv-leaders { width: 300px; }
        .inv--angle .inv-leaders td { padding: 3px 0; vertical-align: bottom; }
        .inv--angle .inv-leaders .inv-fact__label { width: 1%; padding-right: 6px; white-space: nowrap; font-weight: bold; }
        .inv--angle .inv-leaders .inv-dots { width: 100%; border-bottom: 1px dotted #9aa1a9; }
        .inv--angle .inv-leaders .inv-fact__value { width: 1%; padding-left: 8px; white-space: nowrap; text-align: right; }
        .inv--angle .inv-terms { padding-left: 40px; }
        .inv--angle .inv-terms__heading { margin-bottom: 4px; text-align: center; font-size: 11.5px; font-weight: bold; }
        .inv--angle .inv-terms p.inv-pre { color: #4b5563; font-size: 9px; line-height: 1.5; }
        .inv--angle .inv-parties { margin-top: 22px; }
        .inv--angle .inv-parties > tbody > tr > td { width: 50%; padding-right: 24px; }
        .inv--angle .inv-parties__heading { margin-bottom: 4px; color: {{ $design->accentText() }}; font-size: 9px; font-weight: bold; letter-spacing: 0.8px; text-transform: uppercase; }
        .inv--angle .inv-label { color: #6b7280; }
        .inv--angle .inv-lines { margin-top: 22px; border: 1px solid #cfd4da; }
        .inv--angle .inv-lines th { padding: 8px 10px; border: 1px solid {{ $design->color }}; background: {{ $design->color }}; color: {{ $design->ink() }}; font-size: 12px; text-align: center; }
        .inv--angle .inv-lines td { padding: 8px 10px; border: 1px solid #d9dde2; }
        .inv--angle .inv-lines tbody tr:nth-child(even) td { background: {{ $design->tint(0.07) }}; }
        .inv--angle .inv-summary { margin-top: 8px; }
        .inv--angle .inv-summary__spacer { width: 58%; }
        .inv--angle .inv-totals td { padding: 2px 10px; font-size: 12.5px; }
        .inv--angle .inv-bar { margin-top: 10px; background: {{ $design->color }}; color: {{ $design->ink() }}; }
        .inv--angle .inv-bar td { padding: 8px 14px; vertical-align: middle; }
        .inv--angle .inv-bar__total { width: 40%; text-align: right; font-size: 16px; font-weight: bold; }
        .inv--angle .inv-bar__total span { margin-right: 18px; font-size: 13px; font-weight: normal; }
        .inv--angle .inv-bottom { margin-top: 20px; }
        .inv--angle .inv-bottom > tbody > tr > td { width: 33%; padding-right: 18px; font-size: 9.5px; }
        .inv--angle .inv-closing__block { margin-bottom: 8px; }
        .inv--angle .inv-closing__heading, .inv--angle .inv-bottom__heading { margin-bottom: 3px; font-size: 11px; font-weight: bold; }
        .inv--angle .inv-footer { margin-top: 12px; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body class="is-{{ $mode }}">
    @include('invoices.partials.toolbar')
    @php
        $topShape = '<path d="M0 0 H794 V18 L0 104 Z" fill="'.$design->color.'"/>'
            .'<path d="M330 80 L794 26 L794 40 L370 82 Z" fill="'.$design->shade(0.35).'"/>';
        $bottomShape = '<path d="M0 110 L794 110 L794 24 Z" fill="'.$design->color.'"/>'
            .'<path d="M0 92 L520 40 L520 50 L0 102 Z" fill="'.$design->shade(0.35).'"/>';
        $contacts = $design->contactLines();
        $showTerms = $design->shows('notes') && $design->notes;
    @endphp
    @if ($mode === 'pdf')
        <div class="inv-top inv-angle-top">
            <img class="inv-deco" alt="" src="{{ $design->svg($topShape, 794, 120) }}">
            <div class="inv-brand">
                @if ($design->shows('logo') && $design->logo)
                    <span class="inv-chip"><img src="{{ $design->logo }}" alt="{{ $document->sellerName }}"></span>
                @else
                    {{ $document->sellerName }}
                @endif
            </div>
        </div>
        <img class="inv-deco inv-deco--bottom inv-angle-bottom" alt="" src="{{ $design->svg($bottomShape, 794, 110) }}">
    @endif
    <div class="inv-page inv--angle">
    @unless ($mode === 'pdf')
        <div class="inv-top">
            <img class="inv-deco" alt="" src="{{ $design->svg($topShape, 794, 120) }}">
            <div class="inv-brand">
                @if ($design->shows('logo') && $design->logo)
                    <span class="inv-chip"><img src="{{ $design->logo }}" alt="{{ $document->sellerName }}"></span>
                @else
                    {{ $document->sellerName }}
                @endif
            </div>
        </div>
        <img class="inv-deco inv-deco--bottom" alt="" src="{{ $design->svg($bottomShape, 794, 110) }}">
    @endunless

        <h1 class="inv-title">{{ $document->documentTitle }}</h1>

        <table class="inv-intro">
            <tr>
                <td>
                    <table class="inv-leaders">
                        <tr><td class="inv-fact__label">{{ $document->isCreditNote ? __('credit_notes.number') : __('invoices.number') }}:</td><td class="inv-dots"></td><td class="inv-fact__value">{{ $document->number }}</td></tr>
                        <tr><td class="inv-fact__label">{{ __('invoices.customer') }}:</td><td class="inv-dots"></td><td class="inv-fact__value">{{ $document->buyerCompany ?? $document->buyerName }}</td></tr>
                        @if ($document->orderNumber)
                            <tr><td class="inv-fact__label">{{ __('invoices.order_number') }}:</td><td class="inv-dots"></td><td class="inv-fact__value">{{ $document->orderNumber }}</td></tr>
                        @endif
                        @foreach ($document->references() as $reference)
                            <tr><td class="inv-fact__label">{{ $reference['label'] }}:</td><td class="inv-dots"></td><td class="inv-fact__value">{{ $reference['value'] }}</td></tr>
                        @endforeach
                    </table>
                </td>
                <td class="inv-terms">
                    @if ($showTerms)
                        <p class="inv-terms__heading">{{ __('invoices.terms') }}</p>
                        <p class="inv-pre">{{ $design->notes }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <table class="inv-parties">
            <tr>
                <td>
                    <p class="inv-parties__heading">{{ __('invoices.bill_to') }}</p>
                    @include('invoices.partials.buyer')
                </td>
                <td>
                    <p class="inv-parties__heading">{{ __('invoices.seller') }}</p>
                    <p class="inv-strong">{{ $document->sellerName }}</p>
                    @include('invoices.partials.seller', ['withContact' => false])
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
                    </table>
                </td>
            </tr>
        </table>

        <table class="inv-bar">
            <tr>
                <td>
                    @if ($document->isCreditNote)
                        {{ __('credit_notes.related_invoice') }}: {{ $document->relatedInvoiceNumber }}
                    @elseif ($document->isPaid())
                        {{ __('invoices.paid_message', ['date' => $document->paidOn]) }}
                    @elseif ($document->dueOn)
                        {{ __('invoices.due_message', ['date' => $document->dueOn]) }}
                    @else
                        {{ $document->statusLabel }}
                    @endif
                </td>
                <td class="inv-bar__total"><span>{{ $totalLabel }}</span>{{ $document->total }}</td>
            </tr>
        </table>

        <table class="inv-bottom">
            <tr>
                <td>
                    @include('invoices.partials.closing', ['design' => $showTerms ? $design->withoutNotes() : $design])
                </td>
                <td>
                    @if ($contacts !== [])
                        <p class="inv-bottom__heading">{{ __('invoices.contact') }}</p>
                        @foreach ($contacts as $contact)
                            <p>{{ $contact['value'] }}</p>
                        @endforeach
                    @endif
                </td>
                <td>
                    @if ($design->footerText)
                        <p class="inv-footer">{{ $design->footerText }}</p>
                    @endif
                </td>
            </tr>
        </table>

    </div>
</body>
</html>

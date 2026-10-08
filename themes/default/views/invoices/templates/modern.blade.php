<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('theme::invoices.partials.base-styles')
        .invoice-doc--modern { font-size: 12px; line-height: 1.35; }
        .invoice-doc--modern .invoice-doc__band { height: 6px; background: {{ $accent }}; }
        .invoice-doc--modern .invoice-doc__masthead { margin-top: 14px; }
        .invoice-doc--modern .invoice-doc__masthead td { padding: 0; }
        .invoice-doc--modern .invoice-doc__title { margin: 0 0 4px; font-size: 26px; font-weight: bold; letter-spacing: -0.01em; color: #111827; }
        .invoice-doc--modern .invoice-doc__number { font-size: 14px; color: #374151; }
        .invoice-doc--modern .invoice-doc__status { display: inline-block; margin-top: 8px; padding: 2px 10px; border: 1px solid {{ $accent }}; border-radius: 999px; color: #111827; font-size: 11px; }
        .invoice-doc--modern .invoice-doc__from { width: 45%; text-align: right; color: #374151; }
        .invoice-doc--modern .invoice-doc__eyebrow { margin-bottom: 4px; color: #6b7280; font-size: 10px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; }
        .invoice-doc--modern .invoice-doc__facts { margin-top: 16px; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
        .invoice-doc--modern .invoice-doc__facts td { padding: 8px 12px 8px 0; }
        .invoice-doc--modern .invoice-doc__facts p + p { margin-top: 2px; }
        .invoice-doc--modern .invoice-doc__parties { margin-top: 16px; }
        .invoice-doc--modern .invoice-doc__parties td { width: 50%; padding: 0 16px 0 0; }
        .invoice-doc--modern .invoice-doc__card { padding: 10px 12px; border-radius: 6px; background: #f8fafc; }
        .invoice-doc--modern .invoice-doc__lines { margin-top: 18px; }
        .invoice-doc--modern .invoice-doc__lines th { padding: 7px 10px; background: #f1f5f9; color: #374151; font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; }
        .invoice-doc--modern .invoice-doc__lines td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
        .invoice-doc--modern .invoice-doc__summary { margin-top: 12px; }
        .invoice-doc--modern .invoice-doc__summary > tbody > tr > td { padding: 0; }
        .invoice-doc--modern .invoice-doc__spacer { width: 55%; }
        .invoice-doc--modern .invoice-doc__totals td { padding: 3px 10px; }
        .invoice-doc--modern .invoice-doc__grand-total td { padding-top: 8px; border-top: 2px solid {{ $accent }}; font-size: 15px; font-weight: bold; color: #111827; }
        .invoice-doc--modern .invoice-doc__footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <main class="invoice-doc invoice-doc--modern">
        @include('theme::invoices.partials.print-button')

        <div class="invoice-doc__band"></div>

        <table class="invoice-doc__masthead" role="presentation">
            <tr>
                <td>
                    <h1 class="invoice-doc__title">{{ $document->documentTitle }}</h1>
                    <p class="invoice-doc__number">{{ $document->number }}</p>
                    @unless ($document->isCreditNote)
                        <p class="invoice-doc__status">{{ $document->statusLabel }}</p>
                    @endunless
                </td>
                <td class="invoice-doc__from">
                    <p class="invoice-doc__eyebrow">{{ __('invoices.seller') }}</p>
                    @include('theme::invoices.partials.seller')
                </td>
            </tr>
        </table>

        <table class="invoice-doc__facts" role="presentation">
            <tr>
                @foreach ($document->references() as $reference)
                    <td>
                        <p class="invoice-doc__eyebrow">{{ $reference['label'] }}</p>
                        <p class="invoice-doc__strong">{{ $reference['value'] }}</p>
                    </td>
                @endforeach
            </tr>
        </table>

        <table class="invoice-doc__parties" role="presentation">
            <tr>
                <td>
                    <div class="invoice-doc__card">
                        <p class="invoice-doc__eyebrow">{{ __('invoices.bill_to') }}</p>
                        @include('theme::invoices.partials.buyer')
                    </div>
                </td>
                <td>
                    @if ($document->isCreditNote)
                        <div class="invoice-doc__card">
                            <p class="invoice-doc__eyebrow">{{ __('credit_notes.reason') }}</p>
                            <p>{{ $document->reason }}</p>
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        @include('theme::invoices.partials.lines')

        <table class="invoice-doc__summary" role="presentation">
            <tr>
                <td class="invoice-doc__spacer"></td>
                <td>@include('theme::invoices.partials.totals')</td>
            </tr>
        </table>

        <footer class="invoice-doc__footer">{{ $document->sellerName }} · {{ $document->documentTitle }} {{ $document->number }}</footer>
    </main>
</body>
</html>

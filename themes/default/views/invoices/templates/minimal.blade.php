<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('theme::invoices.partials.base-styles')
        .invoice-doc--minimal { font-size: 12px; line-height: 1.45; color: #27272a; }
        .invoice-doc--minimal .invoice-doc__title { margin: 0; color: #71717a; font-size: 11px; font-weight: normal; letter-spacing: 0.18em; text-transform: uppercase; }
        .invoice-doc--minimal .invoice-doc__number { margin-top: 6px; font-family: "DejaVu Serif", Georgia, serif; font-size: 30px; line-height: 1.2; color: #18181b; }
        .invoice-doc--minimal .invoice-doc__status { margin-top: 4px; color: #71717a; }
        .invoice-doc--minimal .invoice-doc__heading { margin: 0 0 6px; color: #71717a; font-size: 10px; font-weight: normal; letter-spacing: 0.14em; text-transform: uppercase; }
        .invoice-doc--minimal .invoice-doc__columns { margin-top: 36px; }
        .invoice-doc--minimal .invoice-doc__columns > tbody > tr > td { width: 33.33%; padding: 0 18px 0 0; }
        .invoice-doc--minimal .invoice-doc__references td { padding: 0 0 2px; }
        .invoice-doc--minimal .invoice-doc__references td + td { text-align: right; }
        .invoice-doc--minimal .invoice-doc__reason { margin-top: 28px; }
        .invoice-doc--minimal .invoice-doc__lines { margin-top: 40px; }
        .invoice-doc--minimal .invoice-doc__lines th { padding: 0 0 8px; border-bottom: 1px solid #18181b; color: #71717a; font-size: 10px; font-weight: normal; letter-spacing: 0.14em; text-transform: uppercase; }
        .invoice-doc--minimal .invoice-doc__lines td { padding: 12px 0; border-bottom: 1px solid #e4e4e7; }
        .invoice-doc--minimal .invoice-doc__lines td + td, .invoice-doc--minimal .invoice-doc__lines th + th { padding-left: 16px; }
        .invoice-doc--minimal .invoice-doc__summary { margin-top: 20px; }
        .invoice-doc--minimal .invoice-doc__summary > tbody > tr > td { padding: 0; }
        .invoice-doc--minimal .invoice-doc__spacer { width: 60%; }
        .invoice-doc--minimal .invoice-doc__totals td { padding: 3px 0; }
        .invoice-doc--minimal .invoice-doc__grand-total td { padding-top: 12px; font-family: "DejaVu Serif", Georgia, serif; font-size: 17px; color: #18181b; }
    </style>
</head>
<body>
    <main class="invoice-doc invoice-doc--minimal">
        @include('theme::invoices.partials.print-button')

        <header>
            <h1 class="invoice-doc__title">{{ $document->documentTitle }}</h1>
            <p class="invoice-doc__number">{{ $document->number }}</p>
            @unless ($document->isCreditNote)
                <p class="invoice-doc__status">{{ $document->statusLabel }}</p>
            @endunless
        </header>

        <table class="invoice-doc__columns" role="presentation">
            <tr>
                <td>
                    <h2 class="invoice-doc__heading">{{ __('invoices.seller') }}</h2>
                    @include('theme::invoices.partials.seller')
                </td>
                <td>
                    <h2 class="invoice-doc__heading">{{ __('invoices.bill_to') }}</h2>
                    @include('theme::invoices.partials.buyer')
                </td>
                <td>
                    <h2 class="invoice-doc__heading">{{ __('invoices.details') }}</h2>
                    <table class="invoice-doc__references" role="presentation">
                        @foreach ($document->references() as $reference)
                            <tr>
                                <td class="invoice-doc__label">{{ $reference['label'] }}</td>
                                <td>{{ $reference['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>

        @if ($document->isCreditNote)
            <section class="invoice-doc__reason">
                <h2 class="invoice-doc__heading">{{ __('credit_notes.reason') }}</h2>
                <p>{{ $document->reason }}</p>
            </section>
        @endif

        @include('theme::invoices.partials.lines')

        <table class="invoice-doc__summary" role="presentation">
            <tr>
                <td class="invoice-doc__spacer"></td>
                <td>@include('theme::invoices.partials.totals')</td>
            </tr>
        </table>
    </main>
</body>
</html>

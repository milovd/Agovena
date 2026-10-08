<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="only light">
    <title>{{ $document->number }}</title>
    <style>
        @include('theme::invoices.partials.base-styles')
        .invoice-doc--compact { font-size: 10.5px; line-height: 1.4; color: #111827; }
        .invoice-doc--compact .invoice-doc__masthead td { padding: 0; }
        .invoice-doc--compact .invoice-doc__seller .invoice-doc__strong { font-size: 13px; }
        .invoice-doc--compact .invoice-doc__idbox { width: 42%; }
        .invoice-doc--compact .invoice-doc__idbox table { border: 1px solid #9ca3af; }
        .invoice-doc--compact .invoice-doc__idbox th { padding: 6px 8px; background: #1f2937; color: #ffffff; font-size: 13px; }
        .invoice-doc--compact .invoice-doc__title { margin: 0; font-size: inherit; }
        .invoice-doc--compact .invoice-doc__idbox td { padding: 3px 8px; border-top: 1px solid #e5e7eb; }
        .invoice-doc--compact .invoice-doc__idbox td + td { text-align: right; font-weight: bold; }
        .invoice-doc--compact .invoice-doc__boxes { margin-top: 10px; border: 1px solid #9ca3af; }
        .invoice-doc--compact .invoice-doc__boxes > tbody > tr > td { width: 50%; padding: 6px 8px; }
        .invoice-doc--compact .invoice-doc__boxes--single { width: 50%; }
        .invoice-doc--compact .invoice-doc__boxes--single > tbody > tr > td { width: auto; }
        .invoice-doc--compact .invoice-doc__boxes > tbody > tr > td + td { border-left: 1px solid #9ca3af; }
        .invoice-doc--compact .invoice-doc__heading { margin: 0 0 3px; color: #4b5563; font-size: 9px; font-weight: bold; letter-spacing: 0.06em; text-transform: uppercase; }
        .invoice-doc--compact .invoice-doc__lines { margin-top: 10px; border: 1px solid #9ca3af; }
        .invoice-doc--compact .invoice-doc__lines th { padding: 5px 8px; background: #1f2937; color: #ffffff; font-size: 9.5px; }
        .invoice-doc--compact .invoice-doc__lines td { padding: 4px 8px; border-top: 1px solid #e5e7eb; }
        .invoice-doc--compact .invoice-doc__lines tbody tr:nth-child(even) td { background: #f9fafb; }
        .invoice-doc--compact .invoice-doc__lines th + th, .invoice-doc--compact .invoice-doc__lines td + td { width: 16%; border-left: 1px solid #e5e7eb; }
        .invoice-doc--compact .invoice-doc__summary { margin-top: 8px; }
        .invoice-doc--compact .invoice-doc__summary > tbody > tr > td { padding: 0; }
        .invoice-doc--compact .invoice-doc__spacer { width: 58%; }
        .invoice-doc--compact .invoice-doc__totals { border: 1px solid #9ca3af; }
        .invoice-doc--compact .invoice-doc__totals td { padding: 3px 8px; }
        .invoice-doc--compact .invoice-doc__grand-total td { border-top: 1px solid #9ca3af; background: #f3f4f6; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <main class="invoice-doc invoice-doc--compact">
        @include('theme::invoices.partials.print-button')

        <table class="invoice-doc__masthead" role="presentation">
            <tr>
                <td class="invoice-doc__seller">
                    <h2 class="invoice-doc__heading">{{ __('invoices.seller') }}</h2>
                    @include('theme::invoices.partials.seller')
                </td>
                <td class="invoice-doc__idbox">
                    <table>
                        <tr>
                            <th colspan="2"><h1 class="invoice-doc__title">{{ $document->documentTitle }} {{ $document->number }}</h1></th>
                        </tr>
                        @unless ($document->isCreditNote)
                            <tr>
                                <td>{{ __('invoices.status_label') }}</td>
                                <td>{{ $document->statusLabel }}</td>
                            </tr>
                        @endunless
                        @foreach ($document->references() as $reference)
                            <tr>
                                <td>{{ $reference['label'] }}</td>
                                <td>{{ $reference['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>

        <table @class(['invoice-doc__boxes', 'invoice-doc__boxes--single' => ! $document->isCreditNote]) role="presentation">
            <tr>
                <td>
                    <h2 class="invoice-doc__heading">{{ __('invoices.bill_to') }}</h2>
                    @include('theme::invoices.partials.buyer')
                </td>
                @if ($document->isCreditNote)
                    <td>
                        <h2 class="invoice-doc__heading">{{ __('credit_notes.reason') }}</h2>
                        <p>{{ $document->reason }}</p>
                    </td>
                @endif
            </tr>
        </table>

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

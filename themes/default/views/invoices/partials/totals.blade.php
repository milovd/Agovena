<table class="invoice-doc__totals">
    <tr>
        <td>{{ __('common.subtotal') }}</td>
        <td class="invoice-doc__num">{{ $document->subtotal }}</td>
    </tr>
    @if ($document->discount)
        <tr>
            <td>{{ __('common.discount') }}</td>
            <td class="invoice-doc__num">{{ $document->discount }}</td>
        </tr>
    @endif
    @if ($document->credit)
        <tr>
            <td>{{ __('common.credit') }}</td>
            <td class="invoice-doc__num">{{ $document->credit }}</td>
        </tr>
    @endif
    <tr>
        <td>{{ $document->taxLabel }}</td>
        <td class="invoice-doc__num">{{ $document->tax }}</td>
    </tr>
    <tr class="invoice-doc__grand-total">
        <td>{{ __('common.total') }}</td>
        <td class="invoice-doc__num">{{ $document->total }}</td>
    </tr>
</table>

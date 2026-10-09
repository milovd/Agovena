{{-- Subtotal, adjustments and tax. The grand total row is rendered by each template. --}}
<tr>
    <td>{{ __('common.subtotal') }}</td>
    <td class="inv-num">{{ $document->subtotal }}</td>
</tr>
@if ($document->discount)
    <tr>
        <td>{{ __('common.discount') }}</td>
        <td class="inv-num">{{ $document->discount }}</td>
    </tr>
@endif
@if ($document->credit)
    <tr>
        <td>{{ __('common.credit') }}</td>
        <td class="inv-num">{{ $document->credit }}</td>
    </tr>
@endif
<tr>
    <td>{{ $document->taxLabel }}</td>
    <td class="inv-num">{{ $document->tax }}</td>
</tr>

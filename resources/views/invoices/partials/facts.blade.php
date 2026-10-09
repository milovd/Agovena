{{-- Document references as label/value rows: number, order, dates, related invoice, status. --}}
@php
    $withNumber = $withNumber ?? true;
    $withStatus = $withStatus ?? false;
@endphp
@if ($withNumber)
    <tr><td class="inv-fact__label">{{ $document->isCreditNote ? __('credit_notes.number') : __('invoices.number') }}</td><td class="inv-fact__value">{{ $document->number }}</td></tr>
@endif
@if ($document->orderNumber)
    <tr><td class="inv-fact__label">{{ __('invoices.order_number') }}</td><td class="inv-fact__value">{{ $document->orderNumber }}</td></tr>
@endif
@foreach ($document->references() as $reference)
    <tr><td class="inv-fact__label">{{ $reference['label'] }}</td><td class="inv-fact__value">{{ $reference['value'] }}</td></tr>
@endforeach
@if ($withStatus)
    <tr><td class="inv-fact__label">{{ __('invoices.status_label') }}</td><td class="inv-fact__value">{{ $document->statusLabel }}</td></tr>
@endif

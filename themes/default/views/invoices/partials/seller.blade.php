<p class="invoice-doc__strong">{{ $document->sellerName }}</p>
@if ($document->sellerAddress)
    <p>{!! nl2br(e($document->sellerAddress)) !!}</p>
@endif
@foreach ($document->sellerIdentifiers() as $identifier)
    <p><span class="invoice-doc__label">{{ $identifier['label'] }}:</span> {{ $identifier['value'] }}</p>
@endforeach

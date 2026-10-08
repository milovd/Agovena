<p class="invoice-doc__strong">{{ $document->buyerName }}</p>
@if ($document->buyerCompany)
    <p>{{ $document->buyerCompany }}</p>
@endif
@foreach ($document->buyerAddressLines() as $addressLine)
    <p>{{ $addressLine }}</p>
@endforeach
@if ($document->buyerPhone)
    <p>{{ $document->buyerPhone }}</p>
@endif
<p>{{ $document->buyerEmail }}</p>
@foreach ($document->buyerProperties as $property)
    <p><span class="invoice-doc__label">{{ $property['label'] }}:</span> {{ $property['value'] }}</p>
@endforeach

<p class="inv-strong">{{ $document->buyerName }}</p>
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
@if ($design->shows('buyer_properties'))
    @foreach ($document->buyerProperties as $property)
        <p><span class="inv-label">{{ $property['label'] }}:</span> {{ $property['value'] }}</p>
    @endforeach
@endif

<p class="invoice-doc__strong">{{ $document->sellerName }}</p>
@if ($document->sellerAddress)
    <p>{!! nl2br(e($document->sellerAddress)) !!}</p>
@endif

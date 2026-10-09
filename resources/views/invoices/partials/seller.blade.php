{{-- Seller identity lines. $labelled prints "Label: value" pairs; $withContact adds email, phone and website. --}}
@php
    $labelled = $labelled ?? false;
    $withContact = $withContact ?? true;
@endphp
@if ($design->shows('seller_address') && $document->sellerAddress)
    @if ($labelled)
        <p><span class="inv-strong">{{ __('invoices.address') }}:</span> {{ str_replace("\n", ', ', trim($document->sellerAddress)) }}</p>
    @else
        <p class="inv-pre">{{ trim($document->sellerAddress) }}</p>
    @endif
@endif
@if ($design->shows('vat_number') && $document->sellerVatNumber)
    <p><span class="{{ $labelled ? 'inv-strong' : 'inv-label' }}">{{ __('invoices.seller_vat_number') }}:</span> {{ $document->sellerVatNumber }}</p>
@endif
@if ($design->shows('company_number') && $document->sellerCompanyNumber)
    <p><span class="{{ $labelled ? 'inv-strong' : 'inv-label' }}">{{ __('invoices.seller_company_number') }}:</span> {{ $document->sellerCompanyNumber }}</p>
@endif
@foreach ($withContact ? $design->contactLines() : [] as $contact)
    <p><span class="{{ $labelled ? 'inv-strong' : 'inv-label' }}">{{ __('invoices.contact_'.$contact['key']) }}:</span> {{ $contact['value'] }}</p>
@endforeach

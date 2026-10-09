{{-- Payment details, notes and the credit note reason, each only when there is something to print. --}}
@php
    $headingClass = $headingClass ?? 'inv-closing__heading';
@endphp
@if ($document->isCreditNote && $document->reason)
    <div class="inv-closing__block">
        <p class="{{ $headingClass }}">{{ __('credit_notes.reason') }}</p>
        <p class="inv-pre">{{ $document->reason }}</p>
    </div>
@endif
@if (! $document->isCreditNote && $design->shows('payment_details') && $design->paymentDetails)
    <div class="inv-closing__block">
        <p class="{{ $headingClass }}">{{ __('invoices.payment_details') }}</p>
        <p class="inv-pre">{{ $design->paymentDetails }}</p>
    </div>
@endif
@if ($design->shows('notes') && $design->notes)
    <div class="inv-closing__block">
        <p class="{{ $headingClass }}">{{ __('invoices.notes') }}</p>
        <p class="inv-pre">{{ $design->notes }}</p>
    </div>
@endif

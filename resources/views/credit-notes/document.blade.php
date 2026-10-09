{{--
    Core credit note document. Uses the same invoice design as invoices.
    A Theme may still replace it by shipping its own `invoices.credit-note` view.
--}}
@include('invoices.layout', [
    'document' => \App\Agovena\Invoices\InvoiceDocumentData::fromCreditNote($creditNote),
    'design' => $design ?? app(\App\Agovena\Invoices\InvoiceDesignRepository::class)->current(),
    'mode' => $mode ?? ($printable ? 'screen' : 'pdf'),
])

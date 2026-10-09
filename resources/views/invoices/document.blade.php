{{--
    Core invoice document. Renders the merchant's invoice design (Admin > Invoices > Design).
    A Theme may still replace it by shipping its own `invoices.document` view.
--}}
@include('invoices.layout', [
    'document' => \App\Agovena\Invoices\InvoiceDocumentData::fromInvoice($invoice),
    'design' => $design ?? app(\App\Agovena\Invoices\InvoiceDesignRepository::class)->current(),
    'mode' => $mode ?? ($printable ? 'screen' : 'pdf'),
])

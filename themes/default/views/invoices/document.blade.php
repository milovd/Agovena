{{-- Theme entry view: core renders it with $invoice and $printable. --}}
@include('theme::invoices.partials.template', [
    'document' => \App\Agovena\Invoices\InvoiceDocumentData::fromInvoice($invoice),
])

{{-- Optional Theme entry view: core renders it with $creditNote and $printable. --}}
@include('theme::invoices.partials.template', [
    'document' => \App\Agovena\Invoices\InvoiceDocumentData::fromCreditNote($creditNote),
])

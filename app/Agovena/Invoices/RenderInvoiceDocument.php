<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class RenderInvoiceDocument
{
    public function __construct(private readonly InvoiceDocumentView $documentView) {}

    public function html(Invoice $invoice): string
    {
        $invoice->loadMissing('items');

        return view($this->documentView->name(), [
            'invoice' => $invoice,
            'printable' => true,
        ])->render();
    }

    public function pdf(Invoice $invoice): string
    {
        $invoice->loadMissing('items');

        return Pdf::loadView($this->documentView->name(), [
            'invoice' => $invoice,
            'printable' => false,
        ])->setPaper('a4')->output();
    }

    public function download(Invoice $invoice): Response
    {
        $filename = $invoice->number.'.pdf';

        return Pdf::loadView($this->documentView->name(), [
            'invoice' => $invoice->loadMissing('items'),
            'printable' => false,
        ])->setPaper('a4')->download($filename);
    }
}

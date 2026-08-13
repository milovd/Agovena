<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Models\CreditNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class RenderCreditNoteDocument
{
    public function html(CreditNote $creditNote): string
    {
        $creditNote->loadMissing(['items', 'invoice']);

        return view('credit-notes.document', [
            'creditNote' => $creditNote,
            'printable' => true,
        ])->render();
    }

    public function pdf(CreditNote $creditNote): string
    {
        $creditNote->loadMissing(['items', 'invoice']);

        return Pdf::loadView('credit-notes.document', [
            'creditNote' => $creditNote,
            'printable' => false,
        ])->setPaper('a4')->output();
    }

    public function download(CreditNote $creditNote): Response
    {
        $filename = $creditNote->number.'.pdf';

        return Pdf::loadView('credit-notes.document', [
            'creditNote' => $creditNote->loadMissing(['items', 'invoice']),
            'printable' => false,
        ])->setPaper('a4')->download($filename);
    }
}

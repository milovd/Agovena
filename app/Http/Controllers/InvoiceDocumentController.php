<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Agovena\Invoices\RenderInvoiceDocument;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Response;

final class InvoiceDocumentController
{
    public function print(Invoice $invoice, RenderInvoiceDocument $documents): Response
    {
        $this->authorizeInvoice($invoice);

        return response($documents->html($invoice), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function pdf(Invoice $invoice, RenderInvoiceDocument $documents): Response
    {
        $this->authorizeInvoice($invoice);

        return $documents->download($invoice);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if ($user->can('invoices.view')) {
            return;
        }

        $customer = authenticated_customer();
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);
    }
}

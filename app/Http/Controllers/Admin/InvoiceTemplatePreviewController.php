<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Invoices\SampleInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class InvoiceTemplatePreviewController
{
    public function __invoke(Request $request, SampleInvoice $sample, InvoiceDocumentView $documentView): Response
    {
        Gate::authorize('theme.view');

        $data = ['invoice' => $sample->make(), 'printable' => true];

        // Lets staff compare a template before saving; the Theme ignores names it does not ship.
        $template = $request->query('template');
        if (is_string($template) && preg_match('/\A[a-z0-9-]{1,40}\z/', $template) === 1) {
            $data['invoiceTemplate'] = $template;
        }

        return response(view($documentView->name(), $data)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}

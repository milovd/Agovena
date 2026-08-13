<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Agovena\Invoices\RenderCreditNoteDocument;
use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Http\Response;

final class CreditNoteDocumentController
{
    public function print(CreditNote $creditNote, RenderCreditNoteDocument $documents): Response
    {
        $this->authorizeCreditNote($creditNote);

        return response($documents->html($creditNote), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function pdf(CreditNote $creditNote, RenderCreditNoteDocument $documents): Response
    {
        $this->authorizeCreditNote($creditNote);

        return $documents->download($creditNote);
    }

    private function authorizeCreditNote(CreditNote $creditNote): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if ($user->can('invoices.view')) {
            return;
        }

        $customer = authenticated_customer();
        abort_unless((int) $creditNote->customer_id === (int) $customer->id, 404);
    }
}

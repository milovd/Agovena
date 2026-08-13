<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CreditNoteIssued;
use App\Notifications\CreditNoteIssuedNotification;
use Illuminate\Support\Facades\Notification;

final class SendCreditNoteIssuedNotification
{
    public function handle(CreditNoteIssued $event): void
    {
        Notification::route('mail', $event->creditNote->customer_email)
            ->notify(new CreditNoteIssuedNotification($event->creditNote));
    }
}

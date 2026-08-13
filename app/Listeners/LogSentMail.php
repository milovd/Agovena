<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Throwable;

final class LogSentMail
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $to = collect($message->getTo())
                ->map(static fn (Address $address): string => $address->getAddress())
                ->filter()
                ->implode(', ');

            if ($to === '') {
                return;
            }

            EmailLog::query()->create([
                'to' => $to,
                'subject' => $message->getSubject(),
                'notification_key' => null,
                'status' => 'sent',
                'error' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Logging must never break checkout or queue workers.
        }
    }
}

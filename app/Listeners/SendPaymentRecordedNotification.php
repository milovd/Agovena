<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Support\Facades\Notification;

final class SendPaymentRecordedNotification
{
    public function handle(PaymentRecorded $event): void
    {
        $event->payment->loadMissing('order');

        Notification::route('mail', $event->payment->order->customer_email)
            ->notify(new PaymentRecordedNotification($event->payment));
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RefundRecorded;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Support\Facades\Notification;

final class SendRefundProcessedNotification
{
    public function handle(RefundRecorded $event): void
    {
        $event->refund->loadMissing('order');

        Notification::route('mail', $event->refund->order->customer_email)
            ->notify(new RefundProcessedNotification($event->refund));
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Notifications\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;

final class SendOrderPlacedNotification implements ShouldQueueAfterCommit
{
    public function handle(OrderCreated $event): void
    {
        Notification::route('mail', $event->order->customer_email)
            ->notify(new OrderPlaced($event->order));
    }
}

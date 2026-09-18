<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Notifications\BackInStockNotifier;
use App\Events\ProductStockChanged;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class DispatchBackInStockNotifications implements ShouldQueueAfterCommit
{
    public function handle(ProductStockChanged $event): void
    {
        if ($event->previousQuantity > 0 || $event->quantity < 1) {
            return;
        }

        app(BackInStockNotifier::class)->dispatch($event->product);
    }
}

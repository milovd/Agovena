<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Credits\ReleaseOrderAccountBalance;
use App\Events\OrderCancelled;

final class ReleaseAccountBalanceWhenOrderCancelled
{
    public function __construct(
        private readonly ReleaseOrderAccountBalance $release,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->release->handle($event->order);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Credits\ReleaseOrderAccountBalance;
use App\Events\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class ReleaseAccountBalanceWhenOrderCancelled implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly ReleaseOrderAccountBalance $release,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->release->handle($event->order);
    }
}

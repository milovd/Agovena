<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Credits\CaptureOrderAccountBalance;
use App\Events\OrderPaid;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class CaptureAccountBalanceWhenOrderPaid implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly CaptureOrderAccountBalance $capture,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->capture->handle($event->order);
    }
}

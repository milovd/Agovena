<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Credits\CaptureOrderAccountBalance;
use App\Events\OrderPaid;

final class CaptureAccountBalanceWhenOrderPaid
{
    public function __construct(
        private readonly CaptureOrderAccountBalance $capture,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->capture->handle($event->order);
    }
}

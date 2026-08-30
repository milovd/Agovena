<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Referrals\ReferralService;
use App\Events\OrderPaid;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class SettleReferralRewardWhenOrderPaid implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(private readonly ReferralService $referrals) {}

    public function handle(OrderPaid $event): void
    {
        $this->referrals->settle($event->order);
    }
}

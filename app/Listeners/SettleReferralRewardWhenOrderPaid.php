<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Referrals\ReferralService;
use App\Events\OrderPaid;

final class SettleReferralRewardWhenOrderPaid
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function handle(OrderPaid $event): void
    {
        $this->referrals->settle($event->order);
    }
}

<?php

declare(strict_types=1);

namespace App\Agovena\Recurring\Listeners;

use App\Agovena\Recurring\Enums\RenewalStatus;
use App\Agovena\Recurring\Models\SubscriptionRenewal;
use App\Events\OrderCancelled;
use Illuminate\Support\Facades\DB;

final class CancelRenewalsWhenOrderCancelled
{
    public function handle(OrderCancelled $event): void
    {
        DB::transaction(function () use ($event): void {
            $renewals = SubscriptionRenewal::query()
                ->where('order_id', $event->order->id)
                ->where('status', RenewalStatus::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($renewals as $renewal) {
                $renewal->status = RenewalStatus::Cancelled;
                $renewal->save();
            }
        });
    }
}

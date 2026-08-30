<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\PlanChanges\ApplyPlanChange;
use App\Events\OrderPaid;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class ApplyPlanChangeWhenOrderPaid implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly ApplyPlanChange $apply,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $request = ProductPlanChangeRequest::query()
            ->where('order_id', $event->order->id)
            ->whereIn('status', ['pending', 'applying'])
            ->first();

        if ($request === null) {
            return;
        }

        $this->apply->handle($request);
    }
}

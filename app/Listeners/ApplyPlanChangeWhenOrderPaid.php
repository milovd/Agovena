<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\PlanChanges\ApplyPlanChange;
use App\Events\OrderPaid;
use App\Models\ProductPlanChangeRequest;

final class ApplyPlanChangeWhenOrderPaid
{
    public function __construct(
        private readonly ApplyPlanChange $apply,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $request = ProductPlanChangeRequest::query()
            ->where('order_id', $event->order->id)
            ->where('status', 'pending')
            ->first();

        if ($request === null) {
            return;
        }

        $this->apply->handle($request);
    }
}

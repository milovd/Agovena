<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning\Listeners;

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Events\PlanChangeApplied;
use App\Models\Product;

final class ApplyPlanChangeToService
{
    public function handle(PlanChangeApplied $event): void
    {
        $subscriptionId = $event->request->subscription_id;
        if ($subscriptionId === null) {
            return;
        }

        $to = Product::query()->find($event->request->to_product_id);
        if ($to === null) {
            return;
        }

        $instances = ServiceInstance::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', '!=', ServiceInstanceStatus::Terminated->value)
            ->get();

        foreach ($instances as $instance) {
            $meta = $instance->meta ?? [];
            $meta['plan_change'] = [
                'from_product_id' => $event->request->from_product_id,
                'to_product_id' => $to->id,
                'applied_at' => now()->toIso8601String(),
            ];
            $instance->product_id = $to->id;
            $instance->meta = $meta;
            $instance->save();
        }
    }
}

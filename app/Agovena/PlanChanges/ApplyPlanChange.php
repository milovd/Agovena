<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Events\PlanChangeApplied;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplyPlanChange
{
    public function handle(ProductPlanChangeRequest $request): ProductPlanChangeRequest
    {
        return DB::transaction(function () use ($request): ProductPlanChangeRequest {
            /** @var ProductPlanChangeRequest $locked */
            $locked = ProductPlanChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'applied') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'plan' => __('notifications.plan_changes.cannot_apply'),
                ]);
            }

            $locked->status = 'applied';
            $locked->save();

            event(new PlanChangeApplied($locked));

            return $locked->fresh() ?? $locked;
        });
    }
}

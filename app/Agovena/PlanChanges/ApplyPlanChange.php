<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PlanChangeApplied;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlanChange;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ApplyPlanChange
{
    public function handle(ProductPlanChangeRequest $request): ProductPlanChangeRequest
    {
        $recoveryState = new \ArrayObject(['manual_review_required' => false]);

        try {
            return DB::transaction(function () use ($request, $recoveryState): ProductPlanChangeRequest {
                /** @var ProductPlanChangeRequest $locked */
                $locked = ProductPlanChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'applied') {
                    return $locked;
                }

                if (! in_array($locked->status, ['pending', 'applying'], true)) {
                    throw ValidationException::withMessages([
                        'plan' => __('notifications.plan_changes.cannot_apply'),
                    ]);
                }

                $mapping = ProductPlanChange::query()
                    ->whereKey($locked->product_plan_change_id)
                    ->where('from_product_id', $locked->from_product_id)
                    ->where('to_product_id', $locked->to_product_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                $source = Product::query()->whereKey($locked->from_product_id)->lockForUpdate()->first();
                $target = Product::query()->whereKey($locked->to_product_id)->lockForUpdate()->first();
                if ($mapping === null
                    || $source === null
                    || $target === null
                    || ! $source->status->isPurchasable()
                    || ! $target->status->isPurchasable()
                    || $source->currency !== $target->currency
                ) {
                    throw ValidationException::withMessages([
                        'plan' => __('notifications.plan_changes.cannot_apply'),
                    ]);
                }

                if (! Customer::query()->whereKey($locked->customer_id)->exists()) {
                    throw ValidationException::withMessages([
                        'plan' => __('notifications.plan_changes.cannot_apply'),
                    ]);
                }

                if ($locked->order_id !== null) {
                    $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->first();
                    $payment = $order?->payment()->lockForUpdate()->first();
                    $items = $order?->items()->lockForUpdate()->get() ?? collect();
                    $rawSnapshot = $order?->getAttribute('custom_properties_snapshot');
                    $snapshot = is_array($rawSnapshot)
                        ? $rawSnapshot
                        : [];
                    $item = $items->count() === 1 ? $items->first() : null;
                    if ($order === null
                        || (int) $order->customer_id !== (int) $locked->customer_id
                        || ($snapshot['origin'] ?? null) !== 'plan_change_surcharge'
                        || $order->status !== OrderStatus::Paid
                        || $payment === null
                        || $payment->status !== PaymentStatus::Paid
                        || $item === null
                        || (int) $item->product_id !== (int) $locked->to_product_id
                        || (int) $item->quantity !== 1
                        || (int) $item->unit_amount !== (int) $item->line_total_amount
                        || (int) $item->line_total_amount !== (int) $order->total_amount
                        || $item->currency !== $target->currency
                        || $payment->currency !== $order->currency
                        || $payment->amount !== $order->total_amount
                        || $order->currency !== $target->currency
                    ) {
                        throw ValidationException::withMessages([
                            'plan' => __('notifications.plan_changes.cannot_apply'),
                        ]);
                    }
                }

                if ($locked->status === 'pending') {
                    $locked->status = 'applying';
                    $locked->save();
                }

                $appliedEvent = new PlanChangeApplied($locked->fresh() ?? $locked);
                try {
                    event($appliedEvent);
                } catch (Throwable $exception) {
                    try {
                        $appliedEvent->compensate();
                    } catch (Throwable $compensationException) {
                        $recoveryState['manual_review_required'] = true;
                        report($compensationException);
                    }

                    if (! $appliedEvent->hasCompensations()) {
                        $recoveryState['manual_review_required'] = true;
                    }

                    throw $exception;
                }

                $locked->status = 'applied';
                $locked->active_request_key = null;
                $locked->save();

                return $locked->fresh() ?? $locked;
            });
        } catch (Throwable $exception) {
            if ($recoveryState['manual_review_required'] === true) {
                ProductPlanChangeRequest::query()
                    ->whereKey($request->id)
                    ->update([
                        'status' => 'manual_review',
                        'active_request_key' => null,
                    ]);
            }

            throw $exception;
        }
    }
}

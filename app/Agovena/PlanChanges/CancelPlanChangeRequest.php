<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelPlanChangeRequest
{
    public function __construct(
        private readonly CancelUnpaidOrder $cancelOrder,
    ) {}

    public function handle(ProductPlanChangeRequest $request): ProductPlanChangeRequest
    {
        $orderId = DB::transaction(function () use ($request): ?int {
            /** @var ProductPlanChangeRequest $locked */
            $locked = ProductPlanChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                return null;
            }

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'plan' => __('notifications.plan_changes.cannot_cancel'),
                ]);
            }

            if ($locked->order_id !== null) {
                /** @var Order|null $order */
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->first();
                $payment = $order?->payment()->lockForUpdate()->first();
                if ($order === null
                    || $order->status !== OrderStatus::Pending
                    || $payment === null
                    || $payment->status === PaymentStatus::Paid
                ) {
                    throw ValidationException::withMessages([
                        'plan' => __('notifications.plan_changes.cannot_cancel'),
                    ]);
                }
            }

            return $locked->order_id;
        });

        if ($orderId !== null) {
            $order = Order::query()->whereKey($orderId)->firstOrFail();
            $this->cancelOrder->handle($order, UnpaidOrderCancelSource::Scheduler);
        }

        return DB::transaction(function () use ($request, $orderId): ProductPlanChangeRequest {
            /** @var ProductPlanChangeRequest $locked */
            $locked = ProductPlanChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'plan' => __('notifications.plan_changes.cannot_cancel'),
                ]);
            }

            if ($orderId !== null && ! Order::query()
                ->whereKey($orderId)
                ->where('status', OrderStatus::Cancelled)
                ->exists()) {
                throw ValidationException::withMessages([
                    'plan' => __('notifications.plan_changes.cannot_cancel'),
                ]);
            }

            $locked->status = 'cancelled';
            $locked->active_request_key = null;
            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }
}

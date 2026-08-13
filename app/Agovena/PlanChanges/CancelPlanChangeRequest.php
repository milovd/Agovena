<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelPlanChangeRequest
{
    public function handle(ProductPlanChangeRequest $request): ProductPlanChangeRequest
    {
        return DB::transaction(function () use ($request): ProductPlanChangeRequest {
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

            $locked->status = 'cancelled';
            $locked->save();

            if ($locked->order_id !== null) {
                /** @var Order|null $order */
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->first();
                if ($order !== null && $order->status === OrderStatus::Pending) {
                    $order->status = OrderStatus::Cancelled;
                    $order->save();
                }

                /** @var Payment|null $payment */
                $payment = Payment::query()->where('order_id', $locked->order_id)->lockForUpdate()->first();
                if ($payment !== null && $payment->status === PaymentStatus::Pending) {
                    $payment->status = PaymentStatus::Cancelled;
                    $payment->save();
                }
            }

            return $locked->fresh() ?? $locked;
        });
    }
}

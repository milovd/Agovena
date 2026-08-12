<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPlanChange;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RequestPlanChange
{
    public function handle(
        Customer $customer,
        Product $from,
        Product $to,
        ?int $subscriptionId = null,
    ): ProductPlanChangeRequest {
        $mapping = ProductPlanChange::query()
            ->where('from_product_id', $from->id)
            ->where('to_product_id', $to->id)
            ->where('is_active', true)
            ->first();

        if ($mapping === null) {
            throw ValidationException::withMessages([
                'plan' => __('notifications.plan_changes.not_allowed'),
            ]);
        }

        if ($from->currency !== $to->currency) {
            throw ValidationException::withMessages([
                'plan' => __('notifications.plan_changes.currency_mismatch'),
            ]);
        }

        return DB::transaction(function () use ($customer, $from, $to, $mapping, $subscriptionId): ProductPlanChangeRequest {
            $difference = max(0, $to->price_amount - $from->price_amount);
            $order = $mapping->timing === 'immediate' && $difference > 0
                ? $this->createOrder($customer, $to, $difference)
                : null;

            return ProductPlanChangeRequest::query()->create([
                'product_plan_change_id' => $mapping->id,
                'customer_id' => $customer->id,
                'subscription_id' => $subscriptionId,
                'from_product_id' => $from->id,
                'to_product_id' => $to->id,
                'order_id' => $order?->id,
                'timing' => $mapping->timing,
                'status' => 'pending',
            ]);
        });
    }

    private function createOrder(Customer $customer, Product $target, int $difference): Order
    {
        $order = Order::query()->create([
            'number' => $this->generateOrderNumber(),
            'status' => OrderStatus::Pending,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'currency' => $target->currency,
            'subtotal_amount' => $difference,
            'shipping_amount' => 0,
            'total_amount' => $difference,
            'shipping_same_as_billing' => true,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $target->id,
            'label' => __('notifications.plan_changes.order_line', ['product' => $target->name]),
            'quantity' => 1,
            'unit_amount' => $difference,
            'line_total_amount' => $difference,
            'currency' => $target->currency,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
            'amount' => $difference,
            'currency' => $target->currency,
        ]);

        return $order;
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'PLAN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}

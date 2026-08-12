<?php

declare(strict_types=1);

namespace Agovena\Modules\Subscriptions;

use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionInterval;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubscriptionService
{
    public function createFromPaidOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()->with('capabilities')->find($item->product_id);
            if ($product === null || ! $product->hasCapability('subscribable')) {
                continue;
            }

            $exists = Subscription::query()
                ->where('order_item_id', $item->id)
                ->exists();
            if ($exists) {
                continue;
            }

            $capability = $product->capability('subscribable');
            $config = $capability !== null ? ($capability->config ?? []) : [];
            $interval = SubscriptionInterval::tryFrom((string) ($config['interval'] ?? 'month'))
                ?? SubscriptionInterval::Month;
            $intervalCount = max(1, (int) ($config['interval_count'] ?? 1));
            $trialDays = max(0, (int) ($config['trial_days'] ?? 0));

            $now = CarbonImmutable::now();
            $trialEnds = $trialDays > 0 ? $now->addDays($trialDays) : null;
            $periodStart = $trialEnds ?? $now;
            $periodEnd = $this->addInterval($periodStart, $interval, $intervalCount);

            Subscription::query()->create([
                'number' => $this->generateNumber(),
                'customer_id' => $order->customer_id,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'product_id' => $product->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'status' => SubscriptionStatus::Active,
                'interval' => $interval,
                'interval_count' => $intervalCount,
                'price_amount' => $item->unit_amount,
                'currency' => $item->currency,
                'quantity' => $item->quantity,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billing_at' => $periodEnd,
                'cancel_at_period_end' => false,
            ]);
        }
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        if (! $subscription->canCancel()) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.cannot_cancel'),
            ]);
        }

        if ($atPeriodEnd && $subscription->status === SubscriptionStatus::Active) {
            $subscription->cancel_at_period_end = true;
            $subscription->cancelled_at = now();
            $subscription->save();

            return $subscription->fresh() ?? $subscription;
        }

        $subscription->status = SubscriptionStatus::Cancelled;
        $subscription->cancel_at_period_end = false;
        $subscription->cancelled_at = now();
        $subscription->ended_at = now();
        $subscription->next_billing_at = null;
        $subscription->save();

        return $subscription->fresh() ?? $subscription;
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.cannot_mark_past_due'),
            ]);
        }

        $subscription->status = SubscriptionStatus::PastDue;
        $subscription->save();

        return $subscription->fresh() ?? $subscription;
    }

    public function end(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Ended) {
            return $subscription;
        }

        $subscription->status = SubscriptionStatus::Ended;
        $subscription->ended_at = now();
        $subscription->next_billing_at = null;
        $subscription->save();

        return $subscription->fresh() ?? $subscription;
    }

    /**
     * Create a pending renewal Order for the next billing period (no payment gateway).
     */
    public function createRenewalOrder(Subscription $subscription): Order
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.cannot_renew'),
            ]);
        }

        if ($subscription->cancel_at_period_end) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.renew_cancelled'),
            ]);
        }

        $pending = SubscriptionRenewal::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', RenewalStatus::Pending)
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.renewal_pending'),
            ]);
        }

        $periodStart = CarbonImmutable::parse($subscription->current_period_end ?? now());
        $periodEnd = $this->addInterval($periodStart, $subscription->interval, $subscription->interval_count);
        $lineTotal = $subscription->price_amount * $subscription->quantity;

        return DB::transaction(function () use ($subscription, $periodStart, $periodEnd, $lineTotal): Order {
            $order = Order::query()->create([
                'number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'customer_id' => $subscription->customer_id,
                'customer_name' => $subscription->customer_name ?? $subscription->customer_email,
                'customer_email' => $subscription->customer_email,
                'currency' => $subscription->currency,
                'subtotal_amount' => $lineTotal,
                'shipping_amount' => 0,
                'total_amount' => $lineTotal,
                'shipping_same_as_billing' => true,
            ]);

            $product = $subscription->product;
            $label = $product !== null
                ? $product->name
                : (string) __('subscriptions::admin.renewal_item');
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $subscription->product_id,
                'label' => $label,
                'quantity' => $subscription->quantity,
                'unit_amount' => $subscription->price_amount,
                'line_total_amount' => $lineTotal,
                'currency' => $subscription->currency,
            ]);

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => PaymentMethod::Manual,
                'status' => PaymentStatus::Pending,
                'amount' => $lineTotal,
                'currency' => $subscription->currency,
            ]);

            SubscriptionRenewal::query()->create([
                'subscription_id' => $subscription->id,
                'order_id' => $order->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => RenewalStatus::Pending,
            ]);

            return $order->fresh(['items', 'payment']) ?? $order;
        });
    }

    public function applyPaidRenewal(Order $order): void
    {
        $renewal = SubscriptionRenewal::query()
            ->where('order_id', $order->id)
            ->where('status', RenewalStatus::Pending)
            ->first();

        if ($renewal === null) {
            return;
        }

        /** @var Subscription $subscription */
        $subscription = Subscription::query()->whereKey($renewal->subscription_id)->firstOrFail();

        $renewal->status = RenewalStatus::Paid;
        $renewal->save();

        $subscription->status = SubscriptionStatus::Active;
        $subscription->current_period_start = $renewal->period_start;
        $subscription->current_period_end = $renewal->period_end;
        $subscription->next_billing_at = $renewal->period_end;
        $subscription->save();
    }

    public function addInterval(CarbonImmutable $from, SubscriptionInterval $interval, int $count): CarbonImmutable
    {
        return match ($interval) {
            SubscriptionInterval::Day => $from->addDays($count),
            SubscriptionInterval::Week => $from->addWeeks($count),
            SubscriptionInterval::Month => $from->addMonthsNoOverflow($count),
            SubscriptionInterval::Year => $from->addYearsNoOverflow($count),
        };
    }

    private function generateNumber(): string
    {
        do {
            $number = 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Subscription::query()->where('number', $number)->exists());

        return $number;
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'REN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}

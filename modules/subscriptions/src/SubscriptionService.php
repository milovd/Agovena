<?php

declare(strict_types=1);

namespace Agovena\Modules\Subscriptions;

use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionInterval;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\PlanChanges\ApplyPlanChange;
use App\Agovena\Subscriptions\ProcessesSubscriptionRenewals;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPlanChangeRequest;
use App\Notifications\SubscriptionCancelledNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubscriptionService implements ProcessesSubscriptionRenewals
{
    public function __construct(
        private readonly IssueInvoiceFromOrder $issueInvoice,
        private readonly ApplyPlanChange $applyPlanChange,
    ) {}

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

            $subscription = Subscription::query()->create([
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

            $this->linkServiceInstances((int) $subscription->id, $item->id);
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
            $this->notifyCancellation($subscription, true);

            return $subscription->fresh() ?? $subscription;
        }

        $subscription->status = SubscriptionStatus::Cancelled;
        $subscription->cancel_at_period_end = false;
        $subscription->cancelled_at = now();
        $subscription->ended_at = now();
        $subscription->next_billing_at = null;
        $subscription->save();
        $this->notifyCancellation($subscription, false);

        return $subscription->fresh() ?? $subscription;
    }

    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->cancel_at_period_end || $subscription->status !== SubscriptionStatus::Active) {
            throw ValidationException::withMessages([
                'subscription' => __('subscriptions::errors.cannot_resume'),
            ]);
        }

        $subscription->cancel_at_period_end = false;
        $subscription->cancelled_at = null;
        $subscription->save();

        return $subscription->fresh() ?? $subscription;
    }

    /**
     * Create at most one renewal order per due period. Safe under overlapping scheduler runs.
     */
    public function processDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $processed = 0;

        $lock = Cache::lock('agovena.subscriptions.process-due', 120);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $ids = Subscription::query()
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->where(function ($query) use ($now): void {
                    $query->where(function ($due) use ($now): void {
                        $due->whereNotNull('next_billing_at')
                            ->where('next_billing_at', '<=', $now);
                    })->orWhere(function ($overdue) use ($now): void {
                        $overdue->whereNotNull('current_period_end')
                            ->where('current_period_end', '<=', $now);
                    });
                })
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                DB::transaction(function () use ($id, $now, &$processed): void {
                    /** @var Subscription|null $subscription */
                    $subscription = Subscription::query()->whereKey($id)->lockForUpdate()->first();
                    if ($subscription === null) {
                        return;
                    }

                    $this->applyDuePlanChanges($subscription);

                    if ($subscription->cancel_at_period_end) {
                        $periodEnd = $subscription->current_period_end;
                        if ($periodEnd !== null && CarbonImmutable::parse($periodEnd)->lessThanOrEqualTo($now)) {
                            $this->cancelPendingRenewals($subscription);
                            $this->end($subscription);
                            $processed++;
                        }

                        return;
                    }

                    $dueAt = $subscription->next_billing_at;
                    if ($dueAt !== null && CarbonImmutable::parse($dueAt)->lessThanOrEqualTo($now)) {
                        $this->ensureRenewalOrder($subscription);
                        $this->markPastDueIfUnpaid($subscription->fresh() ?? $subscription, $now);
                        $processed++;
                    }
                });
            }
        } finally {
            $lock->release();
        }

        return $processed;
    }

    public function ensureRenewalOrder(Subscription $subscription): Order
    {
        $pending = SubscriptionRenewal::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', RenewalStatus::Pending)
            ->first();

        if ($pending !== null && $pending->order_id !== null) {
            $existing = Order::query()->with(['items', 'payment'])->find($pending->order_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->createRenewalOrder($subscription);
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
            $originItem = $subscription->order_item_id !== null
                ? OrderItem::query()->find($subscription->order_item_id)
                : null;
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $subscription->product_id,
                'label' => $label,
                'quantity' => $subscription->quantity,
                'unit_amount' => $subscription->price_amount,
                'line_total_amount' => $lineTotal,
                'currency' => $subscription->currency,
                'options_snapshot' => $originItem->options_snapshot ?? [],
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

            $created = $order->fresh(['items', 'payment']) ?? $order;
            $this->issueInvoice->handle($created);

            return $created;
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

    private function notifyCancellation(Subscription $subscription, bool $atPeriodEnd): void
    {
        $customer = $subscription->customer;
        $name = $customer !== null
            ? (string) $customer->name
            : (string) ($subscription->customer_name ?? '');
        $notification = new SubscriptionCancelledNotification(
            $subscription->number,
            $atPeriodEnd,
            $name,
        );

        if ($customer !== null) {
            $customer->notify($notification);

            return;
        }

        Notification::route('mail', $subscription->customer_email)->notify($notification);
    }

    private function applyDuePlanChanges(Subscription $subscription): void
    {
        $pending = ProductPlanChangeRequest::query()
            ->where('subscription_id', $subscription->id)
            ->where('timing', 'next_period')
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        foreach ($pending as $request) {
            $this->applyPlanChange->handle($request);
        }

        $subscription->refresh();
    }

    private function cancelPendingRenewals(Subscription $subscription): void
    {
        $renewals = SubscriptionRenewal::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', RenewalStatus::Pending)
            ->get();

        foreach ($renewals as $renewal) {
            $renewal->status = RenewalStatus::Cancelled;
            $renewal->save();

            if ($renewal->order_id === null) {
                continue;
            }

            $order = Order::query()->whereKey($renewal->order_id)->lockForUpdate()->first();
            if ($order !== null && $order->status === OrderStatus::Pending) {
                $order->status = OrderStatus::Cancelled;
                $order->save();
            }

            $payment = Payment::query()->where('order_id', $renewal->order_id)->lockForUpdate()->first();
            if ($payment !== null && $payment->status === PaymentStatus::Pending) {
                $payment->status = PaymentStatus::Cancelled;
                $payment->save();
            }
        }
    }

    private function markPastDueIfUnpaid(Subscription $subscription, CarbonImmutable $now): void
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return;
        }

        $periodEnd = $subscription->current_period_end;
        if ($periodEnd === null || CarbonImmutable::parse($periodEnd)->greaterThan($now)) {
            return;
        }

        $unpaid = SubscriptionRenewal::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', RenewalStatus::Pending)
            ->exists();

        if ($unpaid) {
            $this->markPastDue($subscription);
        }
    }

    private function linkServiceInstances(int $subscriptionId, int $orderItemId): void
    {
        if (! Schema::hasTable('service_instances')) {
            return;
        }

        DB::table('service_instances')
            ->where('order_item_id', $orderItemId)
            ->whereNull('subscription_id')
            ->update([
                'subscription_id' => $subscriptionId,
                'updated_at' => now(),
            ]);
    }
}

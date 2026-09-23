<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Recurring\Enums\SubscriptionStatus;
use App\Agovena\Recurring\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Applies provider-owned subscription lifecycle state to Core's subscription
 * projection. Provider-specific payload parsing stays in the Extension.
 */
final class ApplyProviderSubscriptionEvent
{
    public function handle(ProviderSubscriptionEvent $event): bool
    {
        if (! Schema::hasColumn('subscriptions', 'provider_reference')) {
            return false;
        }

        $subscription = Subscription::query()
            ->where('provider_reference', $event->externalSubscriptionId)
            ->first();

        if ($subscription === null && $event->originOrderId !== null && ctype_digit($event->originOrderId)) {
            $subscription = Subscription::query()->where('order_id', (int) $event->originOrderId)->first();
        }

        if ($subscription === null) {
            return false;
        }

        $subscription->provider_reference = $event->externalSubscriptionId;
        $this->applyStatus($subscription, $event);
        $this->applyPeriod($subscription, $event);
        if ($event->cancelAtPeriodEnd !== null) {
            $subscription->cancel_at_period_end = $event->cancelAtPeriodEnd;
        }
        $subscription->save();

        return true;
    }

    private function applyStatus(Subscription $subscription, ProviderSubscriptionEvent $event): void
    {
        if ($event->eventType === 'subscription.canceled' || $event->status === 'canceled') {
            $subscription->status = SubscriptionStatus::Cancelled;
            $subscription->cancelled_at ??= now();
            $subscription->ended_at ??= now();
            $subscription->next_billing_at = null;

            return;
        }

        if ($event->status === 'past_due') {
            $subscription->status = SubscriptionStatus::PastDue;

            return;
        }

        if (in_array($event->status, ['active', 'trialing'], true)) {
            $subscription->status = SubscriptionStatus::Active;
        }
    }

    private function applyPeriod(Subscription $subscription, ProviderSubscriptionEvent $event): void
    {
        if ($event->periodStart !== null) {
            $subscription->current_period_start = CarbonImmutable::parse($event->periodStart);
        }
        if ($event->periodEnd !== null) {
            $subscription->current_period_end = CarbonImmutable::parse($event->periodEnd);
            if ($event->nextBillingAt === null && ! $event->cancelAtPeriodEnd) {
                $subscription->next_billing_at = CarbonImmutable::parse($event->periodEnd);
            }
        }
        if ($event->nextBillingAt !== null) {
            $subscription->next_billing_at = CarbonImmutable::parse($event->nextBillingAt);
        }
    }
}

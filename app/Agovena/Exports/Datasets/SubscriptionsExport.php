<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Agovena\Recurring\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recurring subscriptions with their billing schedule.
 */
final class SubscriptionsExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'subscriptions';
    }

    public function permission(): string
    {
        return 'subscriptions.view';
    }

    public function recordElement(): string
    {
        return 'subscription';
    }

    public function columns(): array
    {
        return [
            'id',
            'number',
            'status',
            'customer_id',
            'customer_email',
            'customer_name',
            'product_id',
            'product_sku',
            'product_name',
            'order_number',
            'interval',
            'interval_count',
            'quantity',
            'price',
            'currency',
            'payment_gateway',
            'renewal_mode',
            'provider_reference',
            'cancel_at_period_end',
            'trial_ends_at',
            'current_period_start',
            'current_period_end',
            'next_billing_at',
            'cancelled_at',
            'ended_at',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Subscription::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Subscription> $query */
        $query = $filters->apply(Subscription::query())->with(['product:id,sku,name', 'order:id,number']);

        foreach ($query->lazyById(500, 'subscriptions.id', 'id') as $subscription) {
            /** @var Subscription $subscription */
            yield [
                'id' => $subscription->id,
                'number' => $subscription->number,
                'status' => $this->values->enum($subscription->status),
                'customer_id' => $subscription->customer_id,
                'customer_email' => $subscription->customer_email,
                'customer_name' => $subscription->customer_name,
                'product_id' => $subscription->product_id,
                'product_sku' => $subscription->product?->sku,
                'product_name' => $subscription->product?->name,
                'order_number' => $subscription->order?->number,
                'interval' => $this->values->enum($subscription->interval),
                'interval_count' => $subscription->interval_count,
                'quantity' => $subscription->quantity,
                'price' => $this->values->money($subscription->price_amount, $subscription->currency),
                'currency' => $subscription->currency,
                'payment_gateway' => $subscription->payment_gateway,
                'renewal_mode' => $subscription->getAttribute('renewal_mode') === null ? null : (string) $subscription->getAttribute('renewal_mode'),
                'provider_reference' => $subscription->provider_reference,
                'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
                'trial_ends_at' => $this->values->dateTime($subscription->trial_ends_at),
                'current_period_start' => $this->values->dateTime($subscription->current_period_start),
                'current_period_end' => $this->values->dateTime($subscription->current_period_end),
                'next_billing_at' => $this->values->dateTime($subscription->next_billing_at),
                'cancelled_at' => $this->values->dateTime($subscription->cancelled_at),
                'ended_at' => $this->values->dateTime($subscription->ended_at),
                'created_at' => $this->values->dateTime($subscription->getAttribute('created_at')),
                'updated_at' => $this->values->dateTime($subscription->getAttribute('updated_at')),
            ];
        }
    }
}

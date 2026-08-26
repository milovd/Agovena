<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Models\ConsentEvent;
use App\Models\Customer;

final class ExportCustomerData
{
    /** @return array<string, mixed> */
    public function handle(Customer $customer): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified_at' => $customer->user?->email_verified_at === null
                    ? null
                    : (string) $customer->user->email_verified_at,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'addresses' => $customer->addresses()->get([
                'label', 'name', 'company', 'line1', 'line2', 'city', 'region',
                'postal_code', 'country', 'phone', 'is_default_billing', 'is_default_shipping',
            ])->toArray(),
            'orders' => $customer->orders()->get([
                'number', 'status', 'subtotal_amount', 'discount_amount', 'tax_amount',
                'shipping_amount', 'credit_amount', 'total_amount', 'currency', 'created_at',
            ])->toArray(),
            'invoices' => $customer->invoices()->get([
                'number', 'status', 'subtotal_amount', 'discount_amount', 'tax_amount',
                'total_amount', 'currency', 'issued_at',
            ])->toArray(),
            'tickets' => $customer->tickets()->get([
                'number', 'subject', 'status', 'priority', 'created_at',
            ])->toArray(),
            'custom_properties' => $customer->propertyValues()
                ->with('definition')
                ->get()
                ->map(static fn ($row): array => [
                    'key' => $row->definition?->key,
                    'label' => $row->definition?->label,
                    'value' => $row->value,
                ])
                ->all(),
            'consent_history' => ConsentEvent::query()
                ->where('user_id', $customer->user_id)
                ->with('categories')
                ->latest('id')
                ->get()
                ->map(static fn (ConsentEvent $event): array => [
                    'consent_version' => $event->consent_version,
                    'choice' => $event->choice,
                    'source' => $event->source,
                    'categories' => $event->categories
                        ->mapWithKeys(static fn ($category): array => [$category->category => (bool) $category->decision])
                        ->all(),
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}

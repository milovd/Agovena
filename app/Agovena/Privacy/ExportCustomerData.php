<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

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
                'email_verified_at' => $customer->email_verified_at === null
                    ? null
                    : (string) $customer->email_verified_at,
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
        ];
    }
}

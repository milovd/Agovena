<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The demo reseed used to wipe customer_property_definitions, which removed the default
 * Paymenter-style address properties from installs and the public demo. This restores them
 * idempotently:
 * - none of the defaults left (a wiped install): restore all eight;
 * - some left (the merchant curated them): only restore the system address keys that the
 *   checkout, tax and invoice mapping depend on, and keep the merchant's own choices.
 * Existing rows are never changed.
 */
return new class extends Migration
{
    private const SYSTEM_KEYS = ['country', 'address', 'address2', 'city', 'state', 'zip'];

    public function up(): void
    {
        $defaults = $this->defaults();
        $existing = DB::table('customer_property_definitions')
            ->whereIn('key', array_keys($defaults))
            ->pluck('key')
            ->all();

        $restore = $existing === []
            ? array_keys($defaults)
            : array_values(array_diff(self::SYSTEM_KEYS, $existing));

        $now = now();
        foreach ($restore as $key) {
            DB::table('customer_property_definitions')->insertOrIgnore([
                ...$defaults[$key],
                'key' => $key,
                'options' => null,
                'is_active' => true,
                'show_on_registration' => true,
                'show_on_checkout' => true,
                'show_on_account' => true,
                'customer_editable' => true,
                'staff_editable' => true,
                'internal_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Restored rows are indistinguishable from the original defaults; keep them.
    }

    /** @return array<string, array<string, mixed>> */
    private function defaults(): array
    {
        $max = static fn (int $length): string => json_encode(['max_length' => $length], JSON_THROW_ON_ERROR);

        return [
            'phone' => ['label' => 'Phone', 'description' => 'Primary customer telephone number.', 'type' => 'phone', 'is_required' => true, 'constraints' => $max(32), 'sort' => 100, 'show_on_invoice' => false],
            'company_name' => ['label' => 'Company Name', 'description' => 'Company associated with the customer.', 'type' => 'text', 'is_required' => false, 'constraints' => $max(255), 'sort' => 101, 'show_on_invoice' => true],
            'country' => ['label' => 'Country', 'description' => 'Customer billing country.', 'type' => 'country', 'is_required' => true, 'constraints' => null, 'sort' => 102, 'show_on_invoice' => true],
            'address' => ['label' => 'Address', 'description' => 'Primary street address.', 'type' => 'text', 'is_required' => true, 'constraints' => $max(255), 'sort' => 103, 'show_on_invoice' => true],
            'address2' => ['label' => 'Address 2', 'description' => 'Apartment, suite, unit, or other address details.', 'type' => 'text', 'is_required' => false, 'constraints' => $max(255), 'sort' => 104, 'show_on_invoice' => false],
            'city' => ['label' => 'City', 'description' => 'Customer billing city.', 'type' => 'text', 'is_required' => true, 'constraints' => $max(255), 'sort' => 105, 'show_on_invoice' => true],
            'state' => ['label' => 'State', 'description' => 'State, province, or region.', 'type' => 'text', 'is_required' => true, 'constraints' => $max(255), 'sort' => 106, 'show_on_invoice' => true],
            'zip' => ['label' => 'ZIP', 'description' => 'ZIP or postal code.', 'type' => 'text', 'is_required' => true, 'constraints' => $max(32), 'sort' => 107, 'show_on_invoice' => true],
        ];
    }
};

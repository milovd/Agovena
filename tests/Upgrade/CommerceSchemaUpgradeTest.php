<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('custom property and product option schema can be applied onto an existing store', function () {
    Artisan::call('migrate');

    $user = User::factory()->create([
        'name' => 'Existing Merchant',
        'email' => 'upgrade-props@example.test',
    ]);
    $customer = $user->ensureCustomer();
    $customerId = $customer->id;

    expect(Schema::hasTable('customer_property_definitions'))->toBeTrue()
        ->and(Schema::hasColumn('order_items', 'options_snapshot'))->toBeTrue();

    Schema::dropIfExists('customer_property_values');
    Schema::dropIfExists('customer_property_definitions');
    Schema::dropIfExists('product_option_choices');
    Schema::dropIfExists('product_options');

    Schema::table('orders', function ($table): void {
        $table->dropColumn('custom_properties_snapshot');
    });
    Schema::table('invoices', function ($table): void {
        $table->dropColumn('custom_properties_snapshot');
    });
    Schema::table('order_items', function ($table): void {
        $table->dropColumn('options_snapshot');
    });

    DB::table('migrations')->whereIn('migration', [
        '2026_08_13_090000_create_customer_custom_properties_tables',
        '2026_08_13_091000_create_product_purchase_options_tables',
    ])->delete();

    expect(Schema::hasTable('customer_property_definitions'))->toBeFalse()
        ->and(Schema::hasColumn('order_items', 'options_snapshot'))->toBeFalse()
        ->and(Customer::query()->whereKey($customerId)->exists())->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasTable('customer_property_definitions'))->toBeTrue()
        ->and(Schema::hasTable('customer_property_values'))->toBeTrue()
        ->and(Schema::hasTable('product_options'))->toBeTrue()
        ->and(Schema::hasTable('product_option_choices'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'custom_properties_snapshot'))->toBeTrue()
        ->and(Schema::hasColumn('invoices', 'custom_properties_snapshot'))->toBeTrue()
        ->and(Schema::hasColumn('order_items', 'options_snapshot'))->toBeTrue()
        ->and(Customer::query()->whereKey($customerId)->value('email'))->toBe($user->email);
});

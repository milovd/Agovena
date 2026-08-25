<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Livewire\Admin\Customers\Index as AdminCustomersIndex;
use App\Livewire\Admin\Invoices\Index as AdminInvoicesIndex;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Products\Index as AdminProductsIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Practical large-data sanity - manageable counts, not millions.
 * Run on MariaDB CI to catch unbounded lists / catastrophic query shapes.
 */
test('admin list pages stay bounded under larger catalogs', function () {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('Large-data sanity targets MariaDB');
    }

    $staff = $this->createStaff();

    Product::factory()->count(200)->active()->create();
    Customer::factory()->count(200)->create();

    $customers = Customer::query()->limit(50)->get();
    foreach ($customers as $customer) {
        for ($n = 0; $n < 4; $n++) {
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
            ]);
            Invoice::query()->create([
                'number' => 'INV-LD-'.Str::upper(Str::random(8)),
                'status' => InvoiceStatus::Issued,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'billing_name' => $customer->name,
                'billing_line1' => '1 Large Data Way',
                'billing_city' => 'Amsterdam',
                'billing_postal_code' => '1000 AA',
                'billing_country' => 'NL',
                'merchant_name' => 'Agovena',
                'issued_at' => now()->toDateString(),
                'subtotal_amount' => $order->subtotal_amount,
                'discount_amount' => 0,
                'credit_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
            ]);
        }
    }

    expect(Product::query()->count())->toBeGreaterThanOrEqual(200)
        ->and(Customer::query()->count())->toBeGreaterThanOrEqual(200)
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(200)
        ->and(Invoice::query()->count())->toBeGreaterThanOrEqual(200);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($staff)->test(AdminProductsIndex::class)->assertOk();
    Livewire::actingAs($staff)->test(AdminCustomersIndex::class)->assertOk();
    Livewire::actingAs($staff)->test(AdminOrdersIndex::class)->assertOk();
    Livewire::actingAs($staff)->test(AdminInvoicesIndex::class)->assertOk();

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries->count())->toBeLessThan(200);

    $this->actingAs($staff)
        ->get(route('admin.products.index'))
        ->assertOk();

    $this->get('/')->assertOk();
});

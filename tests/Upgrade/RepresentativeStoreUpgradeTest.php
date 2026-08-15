<?php

declare(strict_types=1);

/**
 * Representative upgrade rehearsal checkpoint:
 * public schema immediately BEFORE
 *   2026_08_13_090000_create_customer_custom_properties_tables
 *   2026_08_13_091000_create_product_purchase_options_tables
 * with a populated store (user, customer, address, product, order, payment, invoice,
 * module/extension rows). Then run agovena:upgrade (not migrate:fresh).
 */

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('representative store survives upgrade from pre-custom-properties schema', function () {
    Artisan::call('migrate');

    app(ModuleManager::class)->enable('inventory');
    app(ExtensionManager::class)->enable('manual-payment');
    app(SyncRegisteredPermissions::class)(force: true);

    $staff = $this->createStaff();
    $customer = Customer::factory()->create([
        'name' => 'Upgrade Rehearsal Customer',
        'email' => 'upgrade-rehearsal@example.test',
    ]);
    CustomerAddress::query()->create([
        'customer_id' => $customer->id,
        'label' => 'Home',
        'name' => $customer->name,
        'line1' => 'Upgrade Lane 1',
        'city' => 'Utrecht',
        'postal_code' => '3500 AA',
        'country' => 'NL',
        'is_default_billing' => true,
        'is_default_shipping' => true,
    ]);

    $product = Product::factory()->active()->create([
        'name' => 'Upgrade Rehearsal Product',
        'sku' => 'UPG-REHEARSE-1',
        'price_amount' => 3333,
    ]);

    app(CartService::class)->add($product->id, 2);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Upgrade Lane 1',
            'city' => 'Utrecht',
            'postal_code' => '3500 AA',
            'country' => 'NL',
        ]),
    ]);
    app(RecordManualPayment::class)->handle($order, $staff, 'UPG-PAY');

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    $fingerprint = [
        'user_email' => $staff->email,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'product_id' => $product->id,
        'product_sku' => $product->sku,
        'order_id' => $order->id,
        'order_number' => $order->number,
        'order_total' => $order->total_amount,
        'payment_id' => $payment->id,
        'payment_amount' => $payment->amount,
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->number,
        'invoice_total' => $invoice->total_amount,
        'module_inventory' => app(ModuleManager::class)->isEnabled('inventory'),
        'extension_manual' => app(ExtensionManager::class)->isEnabled('manual-payment'),
    ];

    Schema::dropIfExists('customer_property_values');
    Schema::dropIfExists('customer_property_definitions');
    Schema::dropIfExists('product_option_choices');
    Schema::dropIfExists('product_options');

    if (Schema::hasColumn('orders', 'custom_properties_snapshot')) {
        Schema::table('orders', function ($table): void {
            $table->dropColumn('custom_properties_snapshot');
        });
    }
    if (Schema::hasColumn('invoices', 'custom_properties_snapshot')) {
        Schema::table('invoices', function ($table): void {
            $table->dropColumn('custom_properties_snapshot');
        });
    }
    if (Schema::hasColumn('order_items', 'options_snapshot')) {
        Schema::table('order_items', function ($table): void {
            $table->dropColumn('options_snapshot');
        });
    }

    DB::table('migrations')->whereIn('migration', [
        '2026_08_13_090000_create_customer_custom_properties_tables',
        '2026_08_13_091000_create_product_purchase_options_tables',
    ])->delete();

    expect(Schema::hasTable('customer_property_definitions'))->toBeFalse()
        ->and(Order::query()->whereKey($fingerprint['order_id'])->value('number'))->toBe($fingerprint['order_number']);

    $exit = Artisan::call('agovena:upgrade');
    expect($exit)->toBe(0)
        ->and(Schema::hasTable('customer_property_definitions'))->toBeTrue()
        ->and(Schema::hasColumn('order_items', 'options_snapshot'))->toBeTrue()
        ->and(User::query()->where('email', $fingerprint['user_email'])->exists())->toBeTrue()
        ->and(Customer::query()->whereKey($fingerprint['customer_id'])->value('email'))->toBe($fingerprint['customer_email'])
        ->and(Product::query()->whereKey($fingerprint['product_id'])->value('sku'))->toBe($fingerprint['product_sku'])
        ->and(Order::query()->whereKey($fingerprint['order_id'])->value('total_amount'))->toBe($fingerprint['order_total'])
        ->and(Payment::query()->whereKey($fingerprint['payment_id'])->value('amount'))->toBe($fingerprint['payment_amount'])
        ->and(Invoice::query()->whereKey($fingerprint['invoice_id'])->value('number'))->toBe($fingerprint['invoice_number'])
        ->and(Invoice::query()->whereKey($fingerprint['invoice_id'])->value('total_amount'))->toBe($fingerprint['invoice_total'])
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ExtensionManager::class)->isEnabled('manual-payment'))->toBeTrue();
});

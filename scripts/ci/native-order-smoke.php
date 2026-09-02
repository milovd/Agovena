<?php

declare(strict_types=1);

/**
 * Places one paid manual order against the booted application.
 * Used by native deploy smoke - not a storefront browser test.
 */

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

passthru('php '.escapeshellarg(dirname(__DIR__, 2).'/scripts/ci/bootstrap-packages.php').' smoke', $bootstrapExitCode);
if ($bootstrapExitCode !== 0) {
    exit((int) $bootstrapExitCode);
}

$modules = app(ModuleManager::class);
if (! $modules->isInstalled('inventory')) {
    $modules->install('inventory');
}
$modules->enable('inventory');

putenv('AGOVENA_DEV_INSTANT_PAY=true');
$_ENV['AGOVENA_DEV_INSTANT_PAY'] = 'true';
$_SERVER['AGOVENA_DEV_INSTANT_PAY'] = 'true';
config(['agovena.payments.allow_development_instant_pay' => true]);
app(SyncRegisteredPermissions::class)(force: true);

$product = Product::query()->firstOrCreate(
    ['slug' => 'native-smoke-product'],
    [
        'name' => 'Native Smoke Product',
        'sku' => 'NATIVE-SMOKE',
        'description' => 'Release native smoke fixture',
        'status' => ProductStatus::Active,
        'price_amount' => 2500,
        'currency' => 'EUR',
    ],
);
app(ProductCapabilityManager::class)->enable($product, 'physical');

$user = User::query()->firstOrCreate(
    ['email' => 'native-buyer@example.test'],
    [
        'name' => 'Native Buyer',
        'password' => Hash::make('password-password'),
    ],
);
$customer = Customer::query()->firstOrCreate(
    ['email' => 'native-buyer@example.test'],
    [
        'name' => 'Native Buyer',
        'user_id' => $user->id,
    ],
);

$cart = app(CartService::class);
$cart->clear();
$cart->add($product->id, 1);

$order = app(PlaceOrder::class)->handle([
    'customer_name' => $customer->name,
    'customer_email' => $customer->email,
    'customer_id' => $customer->id,
    'payment_method' => 'development',
    'billing' => AddressData::fromArray([
        'name' => $customer->name,
        'line1' => '1 Smoke Street',
        'city' => 'Amsterdam',
        'postal_code' => '1011AA',
        'country' => 'NL',
    ]),
]);

$order = $order->fresh(['payment', 'invoices']);
abort_unless($order instanceof Order, 1);
abort_unless($order->payment?->status === PaymentStatus::Paid, 1);
abort_unless($order->invoices->first() instanceof Invoice, 1);

$invoice = $order->invoices->first();
fwrite(STDOUT, "native-order-ok order={$order->number} invoice={$invoice->number}\n");

<?php

declare(strict_types=1);

/**
 * Places one paid manual order against the booted application.
 * Used by native deploy smoke — not a storefront browser test.
 */

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
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

$modules = app(ModuleManager::class);
if (! $modules->isInstalled('inventory')) {
    $modules->install('inventory');
}
$modules->enable('inventory');

$extensions = app(ExtensionManager::class);
if (! $extensions->isInstalled('manual-payment')) {
    $extensions->install('manual-payment');
}
$extensions->enable('manual-payment');
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
    'billing' => AddressData::fromArray([
        'name' => $customer->name,
        'line1' => '1 Smoke Street',
        'city' => 'Amsterdam',
        'postal_code' => '1011AA',
        'country' => 'NL',
    ]),
]);

$staff = User::query()->where('email', 'native-smoke@example.test')->first()
    ?? User::query()->orderBy('id')->firstOrFail();

app(RecordManualPayment::class)->handle($order, $staff, 'Native smoke paid');

$order = $order->fresh(['payment', 'invoice']);
abort_unless($order instanceof Order, 1);
abort_unless($order->payment?->status === PaymentStatus::Paid, 1);
abort_unless($order->invoice instanceof Invoice, 1);

fwrite(STDOUT, "native-order-ok order={$order->number} invoice={$order->invoice->number}\n");

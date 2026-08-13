<?php

declare(strict_types=1);

use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Digital\Models\DigitalEntitlement;
use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Inventory\Models\InventoryStock;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\ProductOptionType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('mixed physical digital and provisionable subscription cart checks out together', function () {
    $modules = app(ModuleManager::class);
    foreach (['inventory', 'shipping', 'digital', 'subscriptions', 'provisioning'] as $id) {
        $modules->enable($id);
    }
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create([
        'name' => 'Mixed Buyer',
        'email' => 'mixed@example.test',
    ]);
    $capabilities = app(ProductCapabilityManager::class);

    $physical = Product::factory()->active()->create(['name' => 'Desk lamp', 'price_amount' => 2500]);
    $capabilities->enable($physical, 'physical');
    $capabilities->enable($physical, 'inventory');
    $capabilities->enable($physical, 'shippable', ['weight_grams' => 800]);
    app(InventoryService::class)->setQuantity($physical, 4);

    $digital = Product::factory()->active()->create(['name' => 'Field guide PDF', 'price_amount' => 1200]);
    $capabilities->enable($digital, 'digital');
    Storage::fake('local');
    $path = 'digital/'.$digital->id.'/guide.txt';
    Storage::disk('local')->put($path, 'guide');
    DigitalAsset::query()->create([
        'product_id' => $digital->id,
        'label' => 'Guide',
        'disk' => 'local',
        'path' => $path,
        'filename' => 'guide.txt',
        'download_limit' => 3,
        'is_active' => true,
    ]);

    $hosted = Product::factory()->active()->create(['name' => 'Managed VPS', 'price_amount' => 4000]);
    $capabilities->enable($hosted, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $capabilities->enable($hosted, 'provisionable', ['provider_key' => 'manual']);
    $os = ProductOption::query()->create([
        'product_id' => $hosted->id,
        'key' => 'os',
        'label' => 'Operating System',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $os->id,
        'value' => 'ubuntu',
        'label' => 'Ubuntu',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ]);

    $method = ShippingMethod::query()->create([
        'name' => 'Mixed parcel',
        'code' => 'mixed-parcel',
        'type' => ShippingMethodType::Flat,
        'zone_id' => null,
        'config' => ['amount' => 695],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);

    $cart = app(CartService::class);
    $cart->add($physical->id, 1);
    $cart->add($digital->id, 1);
    $cart->add($hosted->id, 1, ['os' => 'ubuntu']);

    expect($cart->requiresShipping())->toBeTrue()
        ->and($cart->itemCount())->toBe(3);

    $requirements = app(CartRequirementComposer::class)->compose($cart);
    expect($requirements->requiresShipping())->toBeTrue()
        ->and($requirements->has(CartRequirement::ShippingAddress))->toBeTrue();

    $billing = AddressData::fromArray([
        'name' => $customer->name,
        'line1' => 'Keizersgracht 1',
        'city' => 'Amsterdam',
        'postal_code' => '1015 CN',
        'country' => 'NL',
    ]);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => $billing,
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect($order->items)->toHaveCount(3)
        ->and($order->shipping_amount)->toBe(695)
        ->and($order->items->firstWhere('product_id', $hosted->id)?->options_snapshot)->not->toBe([]);

    expect(InventoryStock::query()->where('product_id', $physical->id)->value('quantity'))->toBe(3)
        ->and(Invoice::query()->where('order_id', $order->id)->exists())->toBeTrue();

    app(RecordManualPayment::class)->handle($order, $this->createStaff(), 'MIXED-1');

    expect(DigitalEntitlement::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Subscription::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(ServiceInstance::query()->where('order_id', $order->id)->exists())->toBeTrue();
});

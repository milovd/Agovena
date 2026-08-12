<?php

declare(strict_types=1);

use Agovena\Modules\Digital\DigitalDeliveryService;
use Agovena\Modules\Digital\Http\Livewire\Customer\DownloadsIndex;
use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Digital\Models\DigitalEntitlement;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableDigitalModule(): void
{
    app(ModuleManager::class)->enable('digital');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingForDigital(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Digital Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function makeDigitalProduct(array $attrs = []): Product
{
    $product = Product::factory()->active()->create(array_merge(['price_amount' => 1500], $attrs));
    app(ProductCapabilityManager::class)->enable($product, 'digital');

    return $product->fresh(['capabilities']);
}

function attachDigitalAsset(Product $product, int $downloadLimit = 2): DigitalAsset
{
    Storage::fake('local');
    $path = 'digital/'.$product->id.'/sample.txt';
    Storage::disk('local')->put($path, 'digital-file-contents');

    return DigitalAsset::query()->create([
        'product_id' => $product->id,
        'label' => 'Sample PDF',
        'disk' => 'local',
        'path' => $path,
        'filename' => 'sample.txt',
        'download_limit' => $downloadLimit,
        'is_active' => true,
    ]);
}

test('digital module registers capability and account downloads nav', function () {
    expect(app(ProductCapabilityRegistry::class)->has('digital'))->toBeFalse();

    enableDigitalModule();

    expect(app(ProductCapabilityRegistry::class)->has('digital'))->toBeTrue()
        ->and(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->toContain('digital-downloads');
});

test('digital-only cart does not require shipping address', function () {
    enableDigitalModule();
    $product = makeDigitalProduct();

    app(CartService::class)->add($product->id, 1);
    expect(app(CartService::class)->requiresShipping())->toBeFalse();

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Digital',
        'customer_email' => 'digital@example.test',
        'billing' => billingForDigital(),
    ]);

    expect($order->shipping_amount)->toBe(0);
});

test('paid digital order grants entitlements and download limit is enforced', function () {
    enableDigitalModule();
    $customer = Customer::factory()->create();
    $product = makeDigitalProduct();
    $asset = attachDigitalAsset($product, downloadLimit: 1);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForDigital(),
    ]);

    expect(DigitalEntitlement::query()->where('order_id', $order->id)->count())->toBe(0);

    $staff = $this->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'DIG-1');

    $entitlement = DigitalEntitlement::query()->where('order_id', $order->id)->first();
    expect($entitlement)->not->toBeNull()
        ->and($entitlement->digital_asset_id)->toBe($asset->id)
        ->and($entitlement->canDownload())->toBeTrue();

    Livewire::actingAs($customer->user)
        ->test(DownloadsIndex::class)
        ->assertSee('Sample PDF')
        ->assertSee(__('digital::customer.download'));

    $response = $this->actingAs($customer->user)
        ->get(route('customer.downloads.file', $entitlement->token));
    $response->assertOk();

    $entitlement->refresh();
    expect($entitlement->download_count)->toBe(1)
        ->and($entitlement->canDownload())->toBeFalse();

    $this->actingAs($customer->user)
        ->get(route('customer.downloads.file', $entitlement->token))
        ->assertForbidden();
});

test('mixed physical and digital order only grants digital entitlements for digital lines', function () {
    enableDigitalModule();
    app(ModuleManager::class)->enable('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $digital = makeDigitalProduct(['price_amount' => 1000]);
    attachDigitalAsset($digital, downloadLimit: 5);

    $physical = Product::factory()->active()->create(['price_amount' => 2000]);
    app(ProductCapabilityManager::class)->enable($physical, 'physical');
    app(ProductCapabilityManager::class)->enable($physical, 'shippable', ['weight_grams' => 400]);

    $method = ShippingMethod::query()->create([
        'name' => 'Flat',
        'code' => 'flat-mixed',
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 500],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);

    app(CartService::class)->add($digital->id, 1);
    app(CartService::class)->add($physical->id, 1);
    expect(app(CartService::class)->requiresShipping())->toBeTrue();

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForDigital(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    $staff = $this->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff);

    expect(DigitalEntitlement::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(DigitalEntitlement::query()->where('order_id', $order->id)->value('product_id'))->toBe($digital->id);
});

test('digital module disable preserves assets and entitlements', function () {
    enableDigitalModule();
    $product = makeDigitalProduct();
    $asset = attachDigitalAsset($product);
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForDigital(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(DigitalEntitlement::query()->count())->toBe(1);

    app(ModuleManager::class)->disable('digital');

    expect(app(ModuleManager::class)->isEnabled('digital'))->toBeFalse()
        ->and(DigitalAsset::query()->whereKey($asset->id)->exists())->toBeTrue()
        ->and(DigitalEntitlement::query()->count())->toBe(1);
});

test('shipping disabled does not break digital delivery', function () {
    enableDigitalModule();
    expect(app(ModuleManager::class)->isEnabled('shipping'))->toBeFalse();

    $customer = Customer::factory()->create();
    $product = makeDigitalProduct();
    attachDigitalAsset($product);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForDigital(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(app(DigitalDeliveryService::class))->toBeInstanceOf(DigitalDeliveryService::class)
        ->and(DigitalEntitlement::query()->where('order_id', $order->id)->count())->toBe(1);
});

<?php

declare(strict_types=1);

use Agovena\Modules\Shipping\Enums\ShipmentStatus;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\Shipment;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use Agovena\Modules\Shipping\Models\ShippingZone;
use Agovena\Modules\Shipping\ShipmentService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Customer\AddressData;
use App\Agovena\Fulfillment\OrderFulfillmentPresenter;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Customer\Account\OrderShow;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableShippingModule(): void
{
    app(ModuleManager::class)->enable('shipping');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingAddress(string $country = 'NL'): AddressData
{
    return AddressData::fromArray([
        'name' => 'Ship Tester',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => $country,
    ]);
}

function makeShippableProduct(array $attrs = [], int $weightGrams = 500): Product
{
    $product = Product::factory()->active()->create(array_merge(['price_amount' => 2000], $attrs));
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => $weightGrams]);

    return $product->fresh(['capabilities']);
}

function seedFlatMethod(int $amount = 695, ?int $zoneId = null): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Standard',
        'code' => 'standard-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'zone_id' => $zoneId,
        'config' => ['amount' => $amount],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);
}

test('physical cart requires shipping address and method', function () {
    enableShippingModule();
    $method = seedFlatMethod();
    $product = makeShippableProduct();

    expect(app(CartService::class)->requiresShipping())->toBeFalse();

    app(CartService::class)->add($product->id, 1);
    expect(app(CartService::class)->requiresShipping())->toBeTrue();

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'A',
        'customer_email' => 'a@example.test',
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
    ]))->toThrow(ValidationException::class);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'A',
        'customer_email' => 'a@example.test',
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect($order->shipping_amount)->toBe(695)
        ->and($order->total_amount)->toBe(2695)
        ->and(Shipment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('digital-only cart does not require shipping', function () {
    enableShippingModule();
    $product = Product::factory()->active()->create(['price_amount' => 1500]);
    // no shippable capability

    app(CartService::class)->add($product->id, 1);
    expect(app(CartService::class)->requiresShipping())->toBeFalse();

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Digital',
        'customer_email' => 'digital@example.test',
        'billing' => billingAddress(),
    ]);

    expect($order->shipping_amount)->toBe(0)
        ->and(Shipment::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('shipping method calculation covers free flat price weight and zone', function () {
    enableShippingModule();
    $zone = ShippingZone::query()->create([
        'name' => 'Benelux',
        'countries' => ['NL', 'BE'],
        'is_active' => true,
        'sort' => 1,
    ]);

    ShippingMethod::query()->create([
        'name' => 'Free over 50',
        'code' => 'free-50',
        'type' => ShippingMethodType::Free,
        'config' => ['min_subtotal' => 5000, 'amount' => 0],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
    ShippingMethod::query()->create([
        'name' => 'Flat',
        'code' => 'flat',
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 695],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 2,
    ]);
    ShippingMethod::query()->create([
        'name' => 'Price tiers',
        'code' => 'price',
        'type' => ShippingMethodType::Price,
        'config' => ['tiers' => [['min' => 0, 'max' => 3000, 'amount' => 800], ['min' => 3001, 'max' => null, 'amount' => 400]]],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 3,
    ]);
    ShippingMethod::query()->create([
        'name' => 'Weight tiers',
        'code' => 'weight',
        'type' => ShippingMethodType::Weight,
        'config' => ['tiers' => [['min' => 0, 'max' => 600, 'amount' => 500], ['min' => 601, 'max' => null, 'amount' => 1200]]],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 4,
    ]);
    ShippingMethod::query()->create([
        'name' => 'Zone NL',
        'code' => 'zone-nl',
        'type' => ShippingMethodType::Zone,
        'zone_id' => $zone->id,
        'config' => ['amount' => 995],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 5,
    ]);

    $product = makeShippableProduct(['price_amount' => 2500], 500);
    app(CartService::class)->add($product->id, 1);
    $lines = app(CartService::class)->shippableLines();
    $quotes = collect(app(ShippingQuoteResolver::class)->quotes($lines, 'NL', 'EUR'))->keyBy('label');

    expect($quotes->has('Free over 50'))->toBeFalse() // subtotal 2500 < 5000
        ->and($quotes['Flat']->amount->amount)->toBe(695)
        ->and($quotes['Price tiers']->amount->amount)->toBe(800)
        ->and($quotes['Weight tiers']->amount->amount)->toBe(500)
        ->and($quotes['Zone NL']->amount->amount)->toBe(995);

    $usQuotes = app(ShippingQuoteResolver::class)->quotes($lines, 'US', 'EUR');
    expect(collect($usQuotes)->firstWhere('label', 'Zone NL'))->toBeNull();
});

test('mixed cart only puts shippable items on shipment', function () {
    enableShippingModule();
    $method = seedFlatMethod(500);
    $shippable = makeShippableProduct(['name' => 'Mug', 'price_amount' => 1000]);
    $digital = Product::factory()->active()->create(['name' => 'Ebook', 'price_amount' => 800]);

    app(CartService::class)->add($shippable->id, 1);
    app(CartService::class)->add($digital->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Mixed',
        'customer_email' => 'mixed@example.test',
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    $shipment = Shipment::query()->where('order_id', $order->id)->with('items.orderItem')->first();
    expect($shipment)->not->toBeNull()
        ->and($shipment->items)->toHaveCount(1)
        ->and($shipment->items->first()->orderItem?->label)->toBe('Mug')
        ->and($order->items)->toHaveCount(2);
});

test('mark shipped and tracking visible in customer portal', function () {
    enableShippingModule();
    $method = seedFlatMethod();
    $product = makeShippableProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    app(ShipmentService::class)->markShipped(
        $shipment,
        'Generic Carrier',
        'TRACK-123',
        'https://example.test/track/TRACK-123',
    );

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Shipped);

    $views = app(OrderFulfillmentPresenter::class)->forOrder($order->fresh());
    expect($views)->toHaveCount(1)
        ->and($views[0]->trackingNumber)->toBe('TRACK-123')
        ->and($views[0]->carrierName)->toBe('Generic Carrier');

    Livewire::actingAs($customer->user)
        ->test(OrderShow::class, ['order' => $order])
        ->assertOk()
        ->assertSee('TRACK-123')
        ->assertSee('Generic Carrier');
});

test('shipping works when inventory module is not enabled', function () {
    enableShippingModule();
    expect(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse();

    $method = seedFlatMethod(300);
    $product = makeShippableProduct();
    app(CartService::class)->add($product->id, 2);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'NoInv',
        'customer_email' => 'noinv@example.test',
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect($order->shipping_amount)->toBe(300)
        ->and(Shipment::query()->where('order_id', $order->id)->exists())->toBeTrue();
});

test('disabling shipping preserves shipment rows', function () {
    enableShippingModule();
    $method = seedFlatMethod();
    $product = makeShippableProduct();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Keep',
        'customer_email' => 'keep@example.test',
        'billing' => billingAddress(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect(Shipment::query()->where('order_id', $order->id)->exists())->toBeTrue();

    app(ModuleManager::class)->disable('shipping');

    expect(app(ModuleManager::class)->isEnabled('shipping'))->toBeFalse()
        ->and(Shipment::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(ShippingMethod::query()->count())->toBeGreaterThan(0);
});

test('requiresShipping ignores shippable rows when module capability is unregistered', function () {
    $product = Product::factory()->active()->create();
    $product->capabilities()->create(['capability' => 'shippable', 'config' => null]);
    app(CartService::class)->add($product->id, 1);

    expect(app(CartService::class)->requiresShipping())->toBeFalse();
});

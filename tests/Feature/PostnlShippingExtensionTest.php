<?php

declare(strict_types=1);

use Agovena\Extensions\Postnl\HttpPostnlApi;
use Agovena\Extensions\Postnl\PostnlApi;
use Agovena\Extensions\Postnl\PostnlCarrier;
use Agovena\Extensions\Postnl\PostnlShipment;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Checkout\ShippingDestination;
use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Physical\Enums\ShipmentStatus;
use App\Agovena\Physical\Enums\ShippingMethodType;
use App\Agovena\Physical\Models\Shipment;
use App\Agovena\Physical\Models\ShippingMethod;
use App\Agovena\Physical\ShipmentService;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePostnlApi;

uses(CreatesStaff::class);

function enablePostnl(?FakePostnlApi $api = null): FakePostnlApi
{
    require_once optionalExtensionRoot('shipping', 'postnl').DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'PostnlApi.php';
    $api ??= new FakePostnlApi;
    app()->instance(PostnlApi::class, $api);
    installAndEnableModule('shipping');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('postnl');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('postnl', 'api_key', 'test-postnl-key-not-real', secret: true);
    $settings->set('postnl', 'customer_code', 'DEVC');
    $settings->set('postnl', 'customer_number', '12345678');
    $settings->set('postnl', 'sandbox', true);

    return $api;
}

function postnlBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Ship Buyer',
        'line1' => 'Kalverstraat 12',
        'city' => 'Amsterdam',
        'postal_code' => '1012 PH',
        'country' => 'NL',
    ]);
}

function paidShippableOrder(?AddressData $billing = null): Shipment
{
    $billing ??= postnlBilling();
    $customer = Customer::factory()->create(['email' => 'ship-buyer-'.uniqid('', true).'@example.test', 'name' => 'Ship Buyer']);
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 400]);
    $method = ShippingMethod::query()->create([
        'name' => 'Parcel',
        'code' => 'parcel-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 695],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => $billing,
        'shipping_method_id' => $method->id,
    ]);
    app(RecordManualPayment::class)->handle($order, test()->createStaff());

    return Shipment::query()->where('order_id', $order->id)->firstOrFail();
}

test('postnl registers only when the extension is enabled', function () {
    expect(app(ShippingCarrierRegistry::class)->get('postnl'))->toBeNull();
    enablePostnl();
    expect(app(ShippingCarrierRegistry::class)->get('postnl'))->toBeInstanceOf(PostnlCarrier::class);
    app(ExtensionManager::class)->disable('postnl');
    expect(app(ShippingCarrierRegistry::class)->get('postnl'))->toBeNull();
});

test('postnl credentials are encrypted and never redisplayed', function () {
    enablePostnl();
    $row = ExtensionSetting::query()
        ->where('extension_id', 'postnl')
        ->where('key', 'api_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('test-postnl-key-not-real')
        ->and(Crypt::decryptString((string) $row->value))->toBe('test-postnl-key-not-real');
});

test('postnl can quote normalized rates without exposing raw payloads', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    $quotes = app(PostnlCarrier::class)->quote($shipment->order);

    expect($quotes)->toHaveCount(1)
        ->and($quotes[0]->carrierId)->toBe('postnl')
        ->and($quotes[0]->serviceCode)->toBe('3085')
        ->and($quotes[0]->amount)->toBe(695)
        ->and($quotes[0]->currency)->toBe('EUR')
        ->and($api->checkoutCalls)->toBe(1);
});

test('postnl creates a shipment with tracking and a private label', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');

    expect($updated->carrier_id)->toBe('postnl')
        ->and($updated->external_ref)->toStartWith('3SDEVC')
        ->and($updated->tracking_number)->toBe($updated->external_ref)
        ->and($updated->tracking_url)->toContain('jouw.postnl.nl')
        ->and($updated->label_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists((string) $updated->label_path))->toBeTrue()
        ->and($updated->status)->toBe(ShipmentStatus::Processing)
        ->and(PostnlShipment::query()->where('order_id', $updated->order_id)->count())->toBe(1)
        ->and($api->createCalls)->toBe(1);
});

test('retrying carrier create does not open a second postnl shipment', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');
    app(ShipmentService::class)->dispatchCarrier($shipment->fresh() ?? $shipment, 'postnl', '3085');

    expect($api->createCalls)->toBe(1)
        ->and(PostnlShipment::query()->count())->toBe(1);
});

test('invalid address and unsupported destination stay as safe failures', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder(AddressData::fromArray([
        'name' => 'No Number',
        'line1' => 'Kalverstraat',
        'city' => 'Amsterdam',
        'postal_code' => '1012 PH',
        'country' => 'NL',
    ]));

    expect(fn () => app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085'))
        ->toThrow(ValidationException::class);

    $api->unsupportedDestination = true;
    $valid = paidShippableOrder();
    expect(fn () => app(ShipmentService::class)->dispatchCarrier($valid, 'postnl', '3085'))
        ->toThrow(ValidationException::class)
        ->and($api->createCalls)->toBe(0);
});

test('invalid credentials and timeouts do not leak secrets', function () {
    $api = enablePostnl();
    $api->unauthorized = true;
    $shipment = paidShippableOrder();
    expect(fn () => app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085'))
        ->toThrow(ValidationException::class);

    $api->unauthorized = false;
    $api->timeout = true;
    expect(fn () => app(ShipmentService::class)->dispatchCarrier($shipment->fresh() ?? $shipment, 'postnl', '3085'))
        ->toThrow(ValidationException::class);
});

test('label failure keeps the announced barcode so a retry never announces a second parcel', function () {
    $api = enablePostnl();
    $api->failLabel = true;
    $shipment = paidShippableOrder();
    expect(fn () => app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085'))
        ->toThrow(ValidationException::class);

    $row = PostnlShipment::query()->sole();
    expect($row->label_path)->toBeNull()
        ->and($row->provider_status)->toBe('label_failed');

    $api->failLabel = false;
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment->fresh() ?? $shipment, 'postnl', '3085');

    expect($api->createCalls)->toBe(1)
        ->and($api->barcodeCalls)->toBe(1)
        ->and($updated->external_ref)->toBe($row->barcode)
        ->and(PostnlShipment::query()->count())->toBe(1);
});

test('postnl refuses to announce a parcel when its shipment mapping table is missing', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    Schema::drop('postnl_shipments');

    expect(fn () => app(PostnlCarrier::class)->createShipment($shipment->order, '3085'))
        ->toThrow(ValidationException::class)
        ->and($api->barcodeCalls)->toBe(0)
        ->and($api->createCalls)->toBe(0);
});

test('postnl refuses a concurrent shipment creation for the same order', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    $lock = Cache::lock('postnl:create-shipment:'.$shipment->order_id, 30);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(PostnlCarrier::class)->createShipment($shipment->order, '3085'))
            ->toThrow(ValidationException::class)
            ->and($api->barcodeCalls)->toBe(0)
            ->and($api->createCalls)->toBe(0);
    } finally {
        $lock->release();
    }
});

test('postnl delivery options without a tariff never become free shipping quotes', function () {
    $api = enablePostnl();
    $api->unpricedCheckout = true;
    $shipment = paidShippableOrder();

    expect(app(PostnlCarrier::class)->quote($shipment->order))->toBe([])
        ->and($api->checkoutCalls)->toBe(1);
});

test('tracking sync maps delivered without exposing provider internals', function () {
    enablePostnl();
    $shipment = paidShippableOrder();
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');
    $api = app(PostnlApi::class);
    expect($api)->toBeInstanceOf(FakePostnlApi::class);
    $api->nextPhase = '4';
    $synced = app(ShipmentService::class)->syncTracking($updated);

    expect($synced->status)->toBe(ShipmentStatus::Delivered)
        ->and($synced->tracking_url)->not->toContain('api.postnl');
});

test('postnl tracking maps documented phase codes and ignores unknown phases', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');
    $carrier = app(PostnlCarrier::class);

    foreach (['1' => 'processing', '2' => 'shipped', '3' => 'shipped', '4' => 'delivered', '99' => 'processing', '7' => 'processing'] as $phase => $expected) {
        $api->nextPhase = (string) $phase;
        expect($carrier->tracking((string) $updated->external_ref)['status'])->toBe($expected);
    }
});

test('postnl http client uses the documented endpoints and api key header only', function () {
    enablePostnl();
    app()->forgetInstance(PostnlApi::class);
    Http::preventStrayRequests();
    Http::fake([
        'api-sandbox.postnl.nl/shipment/v1_1/barcode*' => Http::response(['Barcode' => '3SDEVC000000001']),
        'api-sandbox.postnl.nl/shipment/v2_2/label*' => Http::response(['ResponseShipments' => []]),
        'api-sandbox.postnl.nl/shipment/v2/status/barcode/*' => Http::response(['CurrentStatus' => []]),
        'api-sandbox.postnl.nl/shipment/v1/checkout' => Http::response(['DeliveryOptions' => []]),
    ]);
    $client = app(HttpPostnlApi::class);

    $client->barcode(['CustomerCode' => 'DEVC', 'CustomerNumber' => '12345678', 'Type' => '3S', 'Serie' => '000000000-999999999']);
    $client->createShipment(['Message' => []], 'postnl-order-1');
    $client->status('3SDEVC000000001');
    $client->checkout(['Options' => ['Daytime']]);

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
    expect($sent)->toHaveCount(4);
    expect($sent[0]->method())->toBe('GET')
        ->and($sent[0]->url())->toStartWith('https://api-sandbox.postnl.nl/shipment/v1_1/barcode?')
        ->and($sent[1]->method())->toBe('POST')
        ->and($sent[1]->url())->toBe('https://api-sandbox.postnl.nl/shipment/v2_2/label?confirm=true')
        ->and($sent[2]->url())->toBe('https://api-sandbox.postnl.nl/shipment/v2/status/barcode/3SDEVC000000001')
        ->and($sent[3]->url())->toBe('https://api-sandbox.postnl.nl/shipment/v1/checkout');
    foreach ($sent as $request) {
        expect($request->header('apikey'))->toBe(['test-postnl-key-not-real'])
            ->and($request->hasHeader('Idempotency-Key'))->toBeFalse();
    }
});

test('tracking failure stays a safe validation error', function () {
    $api = enablePostnl();
    $shipment = paidShippableOrder();
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');
    $api->failTracking = true;
    expect(fn () => app(ShipmentService::class)->syncTracking($updated))
        ->toThrow(ValidationException::class);
});

test('carrier cancellation of a shipped parcel fails without reversing a refund', function () {
    enablePostnl();
    $shipment = paidShippableOrder();
    $updated = app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085');
    app(ShipmentService::class)->markShipped($updated, $updated->carrier_name, $updated->tracking_number, $updated->tracking_url);

    expect(fn () => app(ShipmentService::class)->cancel($updated->fresh() ?? $updated))
        ->toThrow(ValidationException::class);

    $order = $updated->order;
    $staff = $this->createStaff();
    app(RecordRefund::class)->handle($order->payment, $staff, $order->payment->amount, 'customer request');

    expect($updated->fresh()->status)->toBe(ShipmentStatus::Shipped)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('unpaid orders cannot create a carrier shipment', function () {
    enablePostnl();
    $customer = Customer::factory()->create(['email' => 'unpaid-ship@example.test']);
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 400]);
    $method = ShippingMethod::query()->create([
        'name' => 'Parcel',
        'code' => 'parcel-unpaid-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 695],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => postnlBilling(),
        'shipping_method_id' => $method->id,
    ]);
    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and(fn () => app(ShipmentService::class)->dispatchCarrier($shipment, 'postnl', '3085'))
        ->toThrow(ValidationException::class);
});

test('rate unavailable returns no quotes so manual shipping methods remain', function () {
    $api = enablePostnl();
    $api->rateUnavailable = true;
    $shipment = paidShippableOrder();
    expect(app(PostnlCarrier::class)->quote($shipment->order))->toBe([]);
});

test('checkout composes merchant rates with live carrier quotes and keeps manual fallback', function () {
    $api = enablePostnl();
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 400]);
    $method = ShippingMethod::query()->create([
        'name' => 'Counter pickup',
        'code' => 'pickup-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 0],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
    app(CartService::class)->add($product->id, 1);

    $destination = new ShippingDestination(
        country: 'NL',
        postalCode: '1012PH',
        city: 'Amsterdam',
        line1: 'Kalverstraat 12',
    );
    $quotes = app(ShippingQuoteResolver::class)->quotes(
        app(CartService::class)->shippableLines(),
        'NL',
        'EUR',
        $destination,
    );
    $keys = array_map(static fn ($quote) => $quote->key(), $quotes);

    expect($keys)->toContain('method:'.$method->id)
        ->and($keys)->toContain('carrier:postnl:3085')
        ->and($api->checkoutCalls)->toBe(1);

    $api->timeout = true;
    $fallback = app(ShippingQuoteResolver::class)->quotes(
        app(CartService::class)->shippableLines(),
        'NL',
        'EUR',
        $destination,
    );
    $fallbackKeys = array_map(static fn ($quote) => $quote->key(), $fallback);

    expect($fallbackKeys)->toContain('method:'.$method->id)
        ->and($fallbackKeys)->not->toContain('carrier:postnl:3085');
});

test('core and modules do not import postnl types', function () {
    foreach ([base_path('app'), optionalModuleRoot()] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            expect($contents)
                ->not->toContain('Agovena\\Extensions\\Postnl\\')
                ->not->toContain('PostnlCarrier')
                ->not->toContain('api.postnl.nl');
        }
    }
});

<?php

declare(strict_types=1);

use Agovena\Modules\DigitalDelivery\DigitalSecretFulfillmentService;
use Agovena\Modules\DigitalDelivery\Models\DigitalSecretDelivery;
use Agovena\Modules\DigitalDelivery\Models\DigitalSecretItem;
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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableDigitalDeliveryModule(): void
{
    app(ModuleManager::class)->enable('digital-delivery');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingForSecret(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Secret Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function makeSecretProduct(array $attrs = []): Product
{
    $product = Product::factory()->active()->create(array_merge(['price_amount' => 1500], $attrs));
    app(ProductCapabilityManager::class)->enable($product, 'digital_secret', ['source' => 'pool']);

    return $product->fresh(['capabilities']);
}

test('digital delivery module registers capability and account nav', function () {
    expect(app(ProductCapabilityRegistry::class)->has('digital_secret'))->toBeFalse();

    enableDigitalDeliveryModule();

    expect(app(ProductCapabilityRegistry::class)->has('digital_secret'))->toBeTrue()
        ->and(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->toContain('digital-secrets');
});

test('paid digital secret order allocates pool code to customer only', function () {
    enableDigitalDeliveryModule();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $product = makeSecretProduct();

    app(DigitalSecretFulfillmentService::class)->addPoolItems($product, ['KEY-ALPHA-1111', 'KEY-BETA-2222']);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'billing' => billingForSecret(),
    ]);
    app(RecordManualPayment::class)->handle($order, $staff);

    $delivery = DigitalSecretDelivery::query()->where('order_id', $order->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(DigitalSecretDelivery::STATUS_DELIVERED)
        ->and($delivery->value_hint)->toBe('••••1111')
        ->and($delivery->plainValue())->toBe('KEY-ALPHA-1111')
        ->and($delivery->toArray())->not->toHaveKey('value_ciphertext')
        ->and(DigitalSecretItem::query()->where('status', DigitalSecretItem::STATUS_AVAILABLE)->count())->toBe(1);

    $this->actingAs($customer->user)
        ->get(route('customer.digital-secrets'))
        ->assertOk()
        ->assertSee('KEY-ALPHA-1111');

    $this->actingAs($other->user)
        ->get(route('customer.digital-secrets'))
        ->assertOk()
        ->assertDontSee('KEY-ALPHA-1111');
});

test('pool exhaustion blocks checkout before payment', function () {
    enableDigitalDeliveryModule();
    $product = makeSecretProduct();
    app(DigitalSecretFulfillmentService::class)->addPoolItems($product, ['ONLY-ONE']);

    app(CartService::class)->add($product->id, 2);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'Buyer',
        'customer_email' => 'buyer@example.test',
        'billing' => billingForSecret(),
    ]))->toThrow(ValidationException::class);
});

test('secret values are encrypted at rest', function () {
    enableDigitalDeliveryModule();
    $product = makeSecretProduct();
    app(DigitalSecretFulfillmentService::class)->addPoolItems($product, ['SECRET-VALUE-9999']);

    $item = DigitalSecretItem::query()->firstOrFail();
    $raw = $item->getAttributes()['value_ciphertext'] ?? '';

    expect($raw)->not->toBe('SECRET-VALUE-9999')
        ->and(Crypt::decryptString($raw))->toBe('SECRET-VALUE-9999')
        ->and($item->plainValue())->toBe('SECRET-VALUE-9999');
});

test('manual source creates pending delivery for staff assignment', function () {
    enableDigitalDeliveryModule();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 900]);
    app(ProductCapabilityManager::class)->enable($product, 'digital_secret', ['source' => 'manual']);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'billing' => billingForSecret(),
    ]);
    app(RecordManualPayment::class)->handle($order, $staff);

    $delivery = DigitalSecretDelivery::query()->where('order_id', $order->id)->firstOrFail();
    expect($delivery->status)->toBe(DigitalSecretDelivery::STATUS_PENDING_MANUAL)
        ->and($delivery->plainValue())->toBeNull();

    app(DigitalSecretFulfillmentService::class)->assignManual($delivery, 'MANUAL-CODE-7777');

    expect($delivery->fresh()->status)->toBe(DigitalSecretDelivery::STATUS_DELIVERED)
        ->and($delivery->fresh()->plainValue())->toBe('MANUAL-CODE-7777');
});

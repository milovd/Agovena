<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Enums\CustomerPropertyType;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

it('does not replay an idempotent order across customers', function (): void {
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();
    $billing = static fn (Customer $customer): AddressData => AddressData::fromArray([
        'name' => $customer->name,
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);

    app(CartService::class)->add($product->id, 1);
    app(PlaceOrder::class)->handle([
        'customer_name' => $first->name,
        'customer_email' => $first->email,
        'customer_id' => $first->id,
        'idempotency_key' => 'checkout-key-1',
        'billing' => $billing($first),
    ]);
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $second->name,
        'customer_email' => $second->email,
        'customer_id' => $second->id,
        'idempotency_key' => 'checkout-key-1',
        'billing' => $billing($second),
    ]))->toThrow(ValidationException::class);
});

it('backfills a deterministic owner for historical customer orders on replay', function (): void {
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $customer = Customer::factory()->create();
    $billing = AddressData::fromArray([
        'name' => $customer->name,
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'idempotency_key' => 'historical-checkout-key',
        'billing' => $billing,
    ]);
    $order->update(['idempotency_owner_hash' => null]);

    $replay = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'idempotency_key' => 'historical-checkout-key',
        'billing' => $billing,
    ]);

    expect($replay->id)->toBe($order->id)
        ->and($replay->fresh()->idempotency_owner_hash)->toBe(hash('sha256', 'customer|'.$customer->id));
});

it('does not let customer properties spoof the internal plan-change origin', function (): void {
    CustomerPropertyDefinition::query()->create([
        'key' => 'origin',
        'label' => 'Origin',
        'type' => CustomerPropertyType::Text,
        'is_required' => false,
        'sort' => 0,
        'is_active' => true,
        'show_on_checkout' => true,
        'show_on_invoice' => true,
        'customer_editable' => true,
        'staff_editable' => true,
        'internal_only' => false,
    ]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['origin' => 'plan_change_surcharge'],
    ]);

    expect($order->custom_properties_snapshot)->toBe([])
        ->and($customer->propertyValues()->count())->toBe(0);
});

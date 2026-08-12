<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\ProductShow;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('guest can add product to cart and checkout', function () {
    $product = Product::factory()->active()->create([
        'name' => 'Guest Widget',
        'price_amount' => 1500,
        'currency' => 'EUR',
    ]);

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->set('quantity', 2)
        ->call('addToCart')
        ->assertRedirect(route('storefront.cart'));

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Ada Guest')
        ->set('customer_email', 'ada@example.com')
        ->set('billing_name', 'Ada Guest')
        ->set('billing_line1', 'Keizersgracht 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1015 CJ')
        ->set('billing_country', 'NL')
        ->call('placeOrder')
        ->assertRedirect();

    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->customer_name)->toBe('Ada Guest')
        ->and($order->customer_id)->toBeNull()
        ->and($order->billing_line1)->toBe('Keizersgracht 1')
        ->and($order->billing_city)->toBe('Amsterdam')
        ->and($order->billing_country)->toBe('NL')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_amount)->toBe(3000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->unit_amount)->toBe(1500)
        ->and($order->items->first()->line_total_amount)->toBe(3000)
        ->and($order->payment->status)->toBe(PaymentStatus::Pending)
        ->and($order->payment->method->value)->toBe('manual');
});

test('client submitted prices are ignored; server prices are authoritative', function () {
    $product = Product::factory()->active()->create(['price_amount' => 2000]);

    $cart = app(CartService::class);
    $cart->add($product->id, 1);

    $lines = $cart->pricedLines();

    expect($lines[0]->unitPrice->amount)->toBe(2000)
        ->and($lines[0]->lineTotal->amount)->toBe(2000);
});

test('order item snapshots survive product price changes', function () {
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'name' => 'Snap Item']);

    $cart = app(CartService::class);
    $cart->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Snap Buyer',
        'customer_email' => 'snap@example.com',
        'idempotency_key' => 'snap-key-1',
    ]);

    $product->update(['price_amount' => 99999]);

    expect($order->fresh()->items->first()->unit_amount)->toBe(1000)
        ->and($order->fresh()->total_amount)->toBe(1000);
});

test('empty cart cannot checkout', function () {
    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'Nobody',
        'customer_email' => 'nobody@example.com',
    ]))->toThrow(ValidationException::class);
});

test('draft product cannot be added to cart', function () {
    $draft = Product::factory()->draft()->create();

    expect(fn () => app(CartService::class)->add($draft->id))
        ->toThrow(ValidationException::class);
});

test('place order is idempotent for the same key', function () {
    $product = Product::factory()->active()->create(['price_amount' => 500]);
    $cart = app(CartService::class);
    $cart->add($product->id, 1);

    $first = app(PlaceOrder::class)->handle([
        'customer_name' => 'Idem',
        'customer_email' => 'idem@example.com',
        'idempotency_key' => 'same-key',
    ]);

    // Cart cleared — second call with same key returns existing order without creating another.
    $second = app(PlaceOrder::class)->handle([
        'customer_name' => 'Idem',
        'customer_email' => 'idem@example.com',
        'idempotency_key' => 'same-key',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(Order::query()->count())->toBe(1);
});

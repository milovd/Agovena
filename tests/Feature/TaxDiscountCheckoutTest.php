<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Settings\SettingsRepository;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

function addTaxDiscountProduct(int $amount): void
{
    $product = Product::factory()->active()->create([
        'price_amount' => $amount,
        'currency' => 'EUR',
    ]);
    app(CartService::class)->add($product->id);
}

function placeTaxDiscountOrder(array $overrides = [])
{
    return app(PlaceOrder::class)->handle([
        'customer_name' => 'Tax Buyer',
        'customer_email' => 'tax@example.com',
        ...$overrides,
    ]);
}

test('exclusive tax adds tax to total', function () {
    addTaxDiscountProduct(10000);
    TaxRate::query()->create(['name' => 'NL VAT', 'rate_bps' => 2100, 'country' => 'NL']);

    $order = placeTaxDiscountOrder();

    expect($order->subtotal_amount)->toBe(10000)
        ->and($order->tax_amount)->toBe(2100)
        ->and($order->total_amount)->toBe(12100)
        ->and($order->tax_rate_bps)->toBe(2100);
});

test('inclusive tax extracts tax without increasing total', function () {
    app(SettingsRepository::class)->set('store', 'prices_include_tax', true);
    addTaxDiscountProduct(12100);
    TaxRate::query()->create(['name' => 'NL VAT', 'rate_bps' => 2100, 'country' => 'NL']);

    $order = placeTaxDiscountOrder();

    expect($order->tax_amount)->toBe(2100)
        ->and($order->total_amount)->toBe(12100);
});

test('percent coupon reduces total', function () {
    addTaxDiscountProduct(10000);
    DiscountCode::query()->create([
        'code' => 'SAVE10',
        'type' => 'percent',
        'value' => 10,
    ]);

    $order = placeTaxDiscountOrder(['discount_code' => 'save10']);

    expect($order->discount_amount)->toBe(1000)
        ->and($order->discount_code)->toBe('SAVE10')
        ->and($order->total_amount)->toBe(9000);
});

test('invalid and expired coupons fail', function () {
    addTaxDiscountProduct(1000);

    expect(fn () => placeTaxDiscountOrder(['discount_code' => 'NOPE']))
        ->toThrow(ValidationException::class);

    DiscountCode::query()->create([
        'code' => 'OLD',
        'type' => 'percent',
        'value' => 10,
        'ends_at' => now()->subMinute(),
    ]);

    expect(fn () => placeTaxDiscountOrder(['discount_code' => 'OLD']))
        ->toThrow(ValidationException::class);
});

test('redemption is recorded once for idempotent order placement', function () {
    addTaxDiscountProduct(1000);
    DiscountCode::query()->create([
        'code' => 'ONCE',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'EUR',
    ]);

    $first = placeTaxDiscountOrder([
        'discount_code' => 'ONCE',
        'idempotency_key' => 'discount-once',
    ]);
    $second = placeTaxDiscountOrder([
        'discount_code' => 'ONCE',
        'idempotency_key' => 'discount-once',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(DiscountRedemption::query()->where('order_id', $first->id)->count())->toBe(1);
});

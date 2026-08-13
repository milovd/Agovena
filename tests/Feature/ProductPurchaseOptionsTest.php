<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Enums\ProductOptionType;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Validation\ValidationException;

function makeHostedProduct(): Product
{
    $product = Product::factory()->active()->create([
        'name' => 'Cloud VPS',
        'price_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $os = ProductOption::query()->create([
        'product_id' => $product->id,
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
        'label' => 'Ubuntu 22.04',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $os->id,
        'value' => 'debian',
        'label' => 'Debian 12',
        'price_adjustment_amount' => 250,
        'sort' => 2,
        'is_active' => true,
    ]);

    $backup = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'backup',
        'label' => 'Backup',
        'type' => ProductOptionType::Toggle,
        'is_required' => false,
        'is_active' => true,
        'sort' => 2,
        'price_adjustment_amount' => 500,
        'constraints' => [],
    ]);
    expect($backup->key)->toBe('backup');

    return $product->fresh(['purchaseOptions.choices']);
}

test('required product options must be selected before add to cart', function () {
    $product = makeHostedProduct();

    expect(fn () => app(CartService::class)->add($product->id, 1))
        ->toThrow(ValidationException::class);
});

test('selected options adjust price and snapshot onto the order item', function () {
    $product = makeHostedProduct();
    $cart = app(CartService::class);
    $cart->add($product->id, 2, ['os' => 'debian', 'backup' => true]);

    $line = $cart->pricedLines()[0];
    expect($line->unitPrice->amount)->toBe(1750)
        ->and($line->lineTotal->amount)->toBe(3500)
        ->and($line->optionLabels)->toHaveCount(2);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Option Buyer',
        'customer_email' => 'options@example.test',
    ]);

    $item = $order->items->first();
    expect($item->unit_amount)->toBe(1750)
        ->and($item->options_snapshot)->toHaveCount(2)
        ->and(collect($item->options_snapshot)->pluck('key')->all())->toBe(['os', 'backup'])
        ->and(collect($item->options_snapshot)->firstWhere('key', 'os')['value'])->toBe('debian');
});

test('the same product with different options stays as separate cart lines', function () {
    $product = makeHostedProduct();
    $cart = app(CartService::class);
    $cart->add($product->id, 1, ['os' => 'ubuntu']);
    $cart->add($product->id, 1, ['os' => 'debian']);
    $cart->add($product->id, 1, ['os' => 'ubuntu']);

    expect($cart->pricedLines())->toHaveCount(2)
        ->and($cart->itemCount())->toBe(3);
});

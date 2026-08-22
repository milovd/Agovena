<?php

declare(strict_types=1);

use App\Agovena\Checkout\CheckoutFinishProgress;
use App\Agovena\Checkout\CheckoutStep;
use App\Models\Order;

test('finish progress stays three steps when shipping columns are only billing snapshots', function () {
    $order = Order::factory()->create([
        'shipping_line1' => 'Damrak 1',
        'shipping_city' => 'Amsterdam',
        'shipping_postal_code' => '1012 LG',
        'shipping_country' => 'NL',
        'shipping_same_as_billing' => true,
        'shipping_method_label' => null,
        'shipping_carrier_id' => null,
        'shipping_service_code' => null,
        'shipping_amount' => 0,
    ]);

    $items = app(CheckoutFinishProgress::class)->forOrder($order);

    expect($items)->toHaveCount(3)
        ->and(collect($items)->pluck('step')->all())->toBe([
            CheckoutStep::Details,
            CheckoutStep::Payment,
            CheckoutStep::Review,
        ]);
});

test('finish progress includes delivery when a shipping method was chosen', function () {
    $order = Order::factory()->create([
        'shipping_line1' => 'Damrak 1',
        'shipping_method_label' => 'Standard',
        'shipping_amount' => 595,
    ]);

    $items = app(CheckoutFinishProgress::class)->forOrder($order);

    expect($items)->toHaveCount(4)
        ->and(collect($items)->pluck('step')->all())->toBe([
            CheckoutStep::Details,
            CheckoutStep::Delivery,
            CheckoutStep::Payment,
            CheckoutStep::Review,
        ]);
});

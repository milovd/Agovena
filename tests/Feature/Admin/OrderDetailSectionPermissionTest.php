<?php

declare(strict_types=1);

use App\Models\Order;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('order detail opens for staff without shipping or returns permissions', function () {
    $order = Order::factory()->create();
    $staff = $this->createStaff(permissions: ['orders.view']);

    $this->actingAs($staff)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertDontSee('shipping-fulfillment-heading', false);
});

test('order detail shows the fulfillment section to staff who may view shipping', function () {
    $order = Order::factory()->create();
    $staff = $this->createStaff(permissions: ['orders.view', 'shipping.view', 'returns.view']);

    $this->actingAs($staff)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('shipping-fulfillment-heading', false);
});

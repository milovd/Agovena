<?php

declare(strict_types=1);

use App\Agovena\Orders\StorefrontOrderAccess;
use App\Livewire\Storefront\OrderConfirmation;
use App\Livewire\Storefront\PaymentStatusPage;
use App\Models\Customer;
use App\Models\Order;
use Livewire\Livewire;

test('strangers cannot open confirmation or payment status by guessing an order id', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Secret Buyer',
        'customer_email' => 'secret-buyer@example.test',
    ]);
    $intruder = Customer::factory()->create();

    $this->get(route('storefront.order.confirmation', $order))->assertNotFound();
    $this->get(route('storefront.payment.status', $order))->assertNotFound();
    $this->get(route('storefront.order.confirmation', [
        'order' => $order,
        StorefrontOrderAccess::QUERY_KEY => 'definitely-not-the-token',
    ]))->assertNotFound();

    Livewire::actingAs($intruder->user)
        ->test(OrderConfirmation::class, ['order' => $order])
        ->assertNotFound();

    Livewire::actingAs($intruder->user)
        ->test(PaymentStatusPage::class, ['order' => $order])
        ->assertNotFound();
});

test('the storefront token and owning customer can open confirmation', function () {
    $owner = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $owner->id,
        'customer_email' => $owner->email,
        'customer_name' => $owner->name,
    ]);
    $access = app(StorefrontOrderAccess::class);

    $this->get($access->confirmationUrl($order))
        ->assertOk()
        ->assertSee($order->number, false)
        ->assertDontSee($order->storefront_token, false);

    $this->get($access->paymentStatusUrl($order))
        ->assertOk();

    Livewire::actingAs($owner->user)
        ->test(OrderConfirmation::class, ['order' => $order])
        ->assertOk()
        ->assertSee($order->number, false);
});

test('a checkout session can reopen the order without the token', function () {
    $order = Order::factory()->create();
    app(StorefrontOrderAccess::class)->remember($order);

    $this->get(route('storefront.order.confirmation', $order))
        ->assertOk()
        ->assertSee($order->number, false);
});

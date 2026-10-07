<?php

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Orders\Show;
use App\Livewire\Admin\Products\Create;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\ProductShow;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('phase 2 vertical slice end to end with persisted data', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff);

    Livewire::test(Create::class)
        ->set('name', 'Slice Product')
        ->set('status', 'active')
        ->set('price', '19.99')
        ->set('currency', 'EUR')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('slug', 'slice-product')->firstOrFail();
    expect($product->status)->toBe(ProductStatus::Active);

    auth()->logout();

    $this->get('/')->assertOk()->assertSee('Slice Product', false);

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->set('quantity', 1)
        ->call('addToCart')
        ->assertRedirect(route('storefront.cart'));

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->set('quantity', 1)
        ->call('buyNow')
        ->assertRedirect(route('storefront.checkout'));

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Guest Buyer')
        ->set('customer_email', 'guest@example.com')
        ->set('billing_name', 'Guest Buyer')
        ->set('billing_line1', 'Main Street 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1011 AB')
        ->set('billing_country', 'NL')
        ->call('placeOrder')
        ->assertRedirect();

    $order = Order::query()->with(['items', 'payment'])->latest('id')->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->customer_id)->toBeNull()
        ->and($order->items->first()->unit_amount)->toBe(1999)
        ->and($order->payment->status)->toBe(PaymentStatus::Pending);

    $product->update(['price_amount' => 1]);
    expect($order->fresh()->items->first()->unit_amount)->toBe(1999);

    $this->actingAs($staff);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::test(Show::class, ['order' => $order])
        ->call('startRecordPayment')
        ->set('reference', 'MANUAL-1')
        ->call('recordPayment')
        ->assertHasNoErrors();

    $order->refresh()->load('payment');

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->payment->reference)->toBe('MANUAL-1');

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startRecordPayment')
        ->call('recordPayment');

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

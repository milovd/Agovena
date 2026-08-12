<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AttachGuestOrdersToCustomer;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Customer\Account\Dashboard;
use App\Livewire\Customer\Account\OrderShow;
use App\Livewire\Customer\Auth\Login;
use App\Livewire\Customer\Auth\Register;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('customer can register login and open account dashboard', function () {
    Notification::fake();

    app(SettingsRepository::class)->set('store', 'customer_registration', 'optional');

    Livewire::test(Register::class)
        ->set('name', 'Ada Customer')
        ->set('email', 'ada@example.com')
        ->set('password', 'password-secret')
        ->set('password_confirmation', 'password-secret')
        ->call('register')
        ->assertRedirect(route('customer.verification.notice'));

    $customer = Customer::query()->where('email', 'ada@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and(Auth::guard('customer')->check())->toBeTrue();

    $customer->forceFill(['email_verified_at' => now()])->save();

    Livewire::actingAs($customer, 'customer')
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('customer.account.welcome', ['name' => 'Ada Customer']), false);
});

test('logged in customer checkout attaches customer id to order', function () {
    $customer = Customer::factory()->create([
        'name' => 'Linked Buyer',
        'email' => 'linked@example.com',
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1200]);

    app(CartService::class)->add($product->id, 1);

    $this->actingAs($customer, 'customer');

    Livewire::actingAs($customer, 'customer')
        ->test(CheckoutPage::class)
        ->assertSet('customer_name', 'Linked Buyer')
        ->assertSet('customer_email', 'linked@example.com')
        ->set('billing_name', 'Linked Buyer')
        ->set('billing_line1', 'Damrak 10')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 LG')
        ->set('billing_country', 'NL')
        ->call('placeOrder')
        ->assertRedirect();

    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->customer_id)->toBe($customer->id)
        ->and($order->customer_email)->toBe('linked@example.com');
});

test('guest orders attach after customer email is verified', function () {
    $product = Product::factory()->active()->create(['price_amount' => 900]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Guest Later',
        'customer_email' => 'guest-later@example.com',
        'idempotency_key' => 'guest-attach-1',
    ]);

    expect($order->customer_id)->toBeNull();

    $customer = Customer::factory()->unverified()->create([
        'email' => 'guest-later@example.com',
    ]);

    expect(app(AttachGuestOrdersToCustomer::class)->handle($customer))->toBe(0);

    $customer->forceFill(['email_verified_at' => now()])->save();

    expect(app(AttachGuestOrdersToCustomer::class)->handle($customer->fresh()))->toBe(1)
        ->and($order->fresh()->customer_id)->toBe($customer->id);
});

test('customers cannot view another customers order', function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $owner->id,
        'customer_email' => $owner->email,
        'customer_name' => $owner->name,
    ]);

    Livewire::actingAs($intruder, 'customer')
        ->test(OrderShow::class, ['order' => $order])
        ->assertNotFound();
});

test('registration is blocked when disabled in settings', function () {
    app(SettingsRepository::class)->set('store', 'customer_registration', 'disabled');

    Livewire::test(Register::class)
        ->assertRedirect(route('customer.login'));
});

test('required registration redirects guests from checkout to login', function () {
    app(SettingsRepository::class)->set('store', 'customer_registration', 'required');

    $product = Product::factory()->active()->create();
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->assertRedirect(route('customer.login'));
});

test('customer login rejects invalid credentials', function () {
    Customer::factory()->create([
        'email' => 'real@example.com',
        'password' => 'correct-horse',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'real@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});

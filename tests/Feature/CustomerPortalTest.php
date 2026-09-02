<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AccountOverviewCard;
use App\Agovena\Customer\AttachGuestOrdersToCustomer;
use App\Agovena\Customer\CustomerAccountOverview;
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
        ->set('propertyValues.phone', '+31 20 123 4567')
        ->set('propertyValues.country', 'NL')
        ->set('propertyValues.address', 'Customer Street 1')
        ->set('propertyValues.city', 'Amsterdam')
        ->set('propertyValues.state', 'Noord-Holland')
        ->set('propertyValues.zip', '1000 AA')
        ->call('register')
        ->assertRedirect(route('customer.verification.notice'));

    $customer = Customer::query()->where('email', 'ada@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and(Auth::check())->toBeTrue();

    $customer->user?->forceFill(['email_verified_at' => now()])->save();

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('customer.account.welcome', ['name' => 'Ada']), false)
        ->assertSee(__('customer.account.customer_number', ['number' => $customer->id]), false)
        ->assertSee('store-account-dashboard__welcome', false)
        ->assertDontSee('store-account-hero', false)
        ->assertDontSee('ag-avatar', false)
        ->assertDontSee(__('customer.account.cards.open_tickets'), false)
        ->assertDontSee('store-account-dashboard__referral-card', false)
        ->assertSee('ag-empty', false);
});

test('customer account dashboard receives registered overview cards', function () {
    $customer = Customer::factory()->create();

    app(CustomerAccountOverview::class)->register(
        'downloads',
        static fn (Customer $cardCustomer): AccountOverviewCard => new AccountOverviewCard(
            id: 'downloads',
            label: 'customer.account.cards.downloads',
            countOrValue: $cardCustomer->is($customer) ? '3' : '0',
            routeName: 'customer.orders.index',
            sort: 20,
        ),
        20,
    );

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertViewHas('overviewCards', static function (array $cards): bool {
            foreach ($cards as $card) {
                if ($card->id === 'downloads' && $card->countOrValue === '3') {
                    return true;
                }
            }

            return false;
        });
});

test('logged in customer checkout attaches customer id to order', function () {
    $customer = Customer::factory()->create([
        'name' => 'Linked Buyer',
        'email' => 'linked@example.com',
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1200]);

    app(CartService::class)->add($product->id, 1);

    $this->actingAs($customer->user);

    Livewire::actingAs($customer->user)
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

    $customer->user?->forceFill(['email_verified_at' => now()])->save();

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

    Livewire::actingAs($intruder->user)
        ->test(OrderShow::class, ['order' => $order])
        ->assertNotFound();
});

test('registration is blocked when disabled in settings', function () {
    app(SettingsRepository::class)->set('store', 'customer_registration', 'disabled');

    Livewire::test(Register::class)
        ->assertRedirect(route('login'));
});

test('required registration redirects guests from checkout to login', function () {
    app(SettingsRepository::class)->set('store', 'customer_registration', 'required');

    $product = Product::factory()->active()->create();
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->assertRedirect(route('login'));
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

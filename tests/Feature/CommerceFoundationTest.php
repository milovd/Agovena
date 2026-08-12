<?php

use App\Agovena\PlanChanges\RequestPlanChange;
use App\Enums\ProductStatus;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\ProductShow;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlanChange;
use App\Models\ProductPlanChangeRequest;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('placing an order sends the order notification to the customer email', function () {
    Notification::fake();
    $product = Product::factory()->create([
        'name' => 'Notified product',
        'slug' => 'notified-product',
        'status' => ProductStatus::Active,
        'price_amount' => 2500,
        'currency' => 'EUR',
    ]);

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->set('quantity', 1)
        ->call('addToCart');

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Mail Customer')
        ->set('customer_email', 'mail-customer@example.com')
        ->set('billing_name', 'Mail Customer')
        ->set('billing_line1', 'Main Street 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1011 AB')
        ->set('billing_country', 'NL')
        ->call('placeOrder')
        ->assertHasNoErrors();

    Notification::assertSentOnDemand(
        OrderPlaced::class,
        fn (OrderPlaced $notification, array $channels, object $notifiable): bool => in_array('mail', $channels, true)
            && $notifiable->routeNotificationFor('mail') === 'mail-customer@example.com',
    );
});

test('an allowed immediate plan upgrade creates a pending difference order', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    $request = app(RequestPlanChange::class)->handle($customer, $from, $to);

    expect($request)->toBeInstanceOf(ProductPlanChangeRequest::class)
        ->and($request->order_id)->not->toBeNull()
        ->and(Order::query()->findOrFail($request->order_id)->total_amount)->toBe(1500)
        ->and(Order::query()->findOrFail($request->order_id)->items()->firstOrFail()->label)
        ->toBe('Plan change to '.$to->name);
});

test('a disallowed plan change is rejected', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->create();
    $to = Product::factory()->create();

    expect(fn () => app(RequestPlanChange::class)->handle($customer, $from, $to))
        ->toThrow(ValidationException::class);
});

test('make module creates a safe scaffold and refuses overwrite', function () {
    $path = base_path('modules/testgenmod');
    File::deleteDirectory($path);

    try {
        $this->artisan('agovena:make-module', ['id' => 'testgenmod'])
            ->assertSuccessful();

        expect(File::exists($path.'/module.json'))->toBeTrue()
            ->and(File::exists($path.'/src/TestgenmodModule.php'))->toBeTrue()
            ->and(File::exists($path.'/src/TestgenmodServiceProvider.php'))->toBeTrue();

        $this->artisan('agovena:make-module', ['id' => 'testgenmod'])
            ->assertFailed();
    } finally {
        File::deleteDirectory($path);
    }
});

test('generators reject unsafe ids', function () {
    $this->artisan('agovena:make-theme', ['id' => '../unsafe'])
        ->assertFailed();
});

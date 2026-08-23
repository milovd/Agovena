<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Support\CreateTicket;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('core storefront account and admin surfaces render without server errors', function () {
    $staff = $this->createStaff();
    $category = Category::factory()->create(['name' => 'Phones', 'slug' => 'phones']);
    $product = Product::factory()->active()->create([
        'name' => 'Smoke Phone',
        'slug' => 'smoke-phone',
        'category_id' => $category->id,
        'price_amount' => 1999,
    ]);
    $customer = Customer::factory()->create([
        'name' => 'Smoke Customer',
        'email' => 'smoke-customer@example.test',
    ]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
    ]);
    $ticket = app(CreateTicket::class)->handle($customer, 'Smoke ticket', 'Need a receipt.');

    $storefront = [
        '/',
        '/?q=Smoke',
        '/products/'.$product->slug,
        '/categories',
        '/categories/'.$category->slug,
        '/cart',
        '/login',
        '/register',
        '/search/suggest?q=Smoke',
    ];

    foreach ($storefront as $uri) {
        $response = $this->get($uri);
        expect($response->status())->toBe(200, $uri.' -> '.$response->status().' '.$response->headers->get('Location', ''))
            ->and($response->getContent() ?? '')->not->toContain('SQLSTATE');
    }

    $this->get('/products/missing-smoke-product')
        ->assertNotFound()
        ->assertSee(__('errors.404.heading'), false);

    $this->get('/checkout')->assertRedirect(route('storefront.cart'));
    app(CartService::class)->add($product->id, 1);
    $this->get('/checkout')
        ->assertOk()
        ->assertDontSee('SQLSTATE', false);

    $this->actingAs($customer->user);
    $account = [
        route('customer.account'),
        route('customer.profile'),
        route('customer.addresses'),
        route('customer.orders.index'),
        route('customer.orders.show', $order),
        route('customer.invoices.index'),
        route('customer.credits'),
        route('customer.tickets.index'),
        route('customer.tickets.create'),
        route('customer.tickets.show', $ticket),
    ];
    foreach ($account as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertDontSee('SQLSTATE', false);
    }

    $this->actingAs($customer->user)
        ->get('/admin')
        ->assertForbidden()
        ->assertSee(__('errors.403.heading'), false);

    $this->actingAs($staff);
    $admin = [
        route('admin.dashboard'),
        route('admin.products.index'),
        route('admin.products.create'),
        route('admin.products.edit', $product),
        route('admin.categories.index'),
        route('admin.customers.index'),
        route('admin.customers.show', $customer),
        route('admin.customers.properties'),
        route('admin.orders.index'),
        route('admin.orders.show', $order),
        route('admin.invoices.index'),
        route('admin.discounts.index'),
        route('admin.taxes.index'),
        route('admin.plan-changes.index'),
        route('admin.tickets.index'),
        route('admin.tickets.show', $ticket),
        route('admin.currencies.index'),
        route('admin.settings.index'),
        route('admin.settings.edit', 'general'),
        route('admin.users.index'),
        route('admin.roles.index'),
        route('admin.security.two-factor'),
        route('admin.audit.index'),
        route('admin.updates'),
        route('admin.api-tokens'),
        route('admin.notifications'),
        route('admin.email-log'),
        route('admin.failed-jobs'),
        route('admin.cron-statistics'),
        route('admin.settings.edit', 'mail'),
        route('admin.modules.index'),
        route('admin.extensions.index'),
        route('admin.appearance.themes'),
        route('admin.appearance.customize'),
        route('admin.appearance.pages'),
        route('admin.appearance.navigation'),
    ];
    foreach ($admin as $uri) {
        $this->actingAs($staff)
            ->get($uri)
            ->assertOk()
            ->assertDontSee('SQLSTATE', false)
            ->assertDontSee('Server Error', false);
    }
});

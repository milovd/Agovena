<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('same-origin api remains cookie-stateful', function () {
    $api = app('router')->getMiddlewareGroups()['api'] ?? [];

    expect($api)->toContain(EnsureFrontendRequestsAreStateful::class);
});

test('catalog endpoints are public and paginated', function () {
    Product::factory()->active()->create(['name' => 'Public lamp', 'slug' => 'public-lamp']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Public lamp')
        ->assertJsonStructure(['data', 'links', 'meta']);

    $this->getJson('/api/v1/products/public-lamp')
        ->assertOk()
        ->assertJsonPath('data.slug', 'public-lamp');

    $this->getJson('/api/v1/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.3');
});

test('token auth returns the secret once and scopes orders to the owner', function () {
    $alice = Customer::factory()->create([
        'email' => 'alice-api@example.test',
    ]);
    $bob = Customer::factory()->create();
    $aliceOrder = Order::factory()->create([
        'customer_id' => $alice->id,
        'customer_email' => $alice->email,
        'customer_name' => $alice->name,
    ]);
    $bobOrder = Order::factory()->create([
        'customer_id' => $bob->id,
        'customer_email' => $bob->email,
        'customer_name' => $bob->name,
    ]);

    $created = $this->postJson('/api/v1/auth/tokens', [
        'email' => $alice->email,
        'password' => 'password',
        'name' => 'Headless',
    ])->assertCreated()->json();

    expect($created['token'])->toBeString();

    $this->withToken($created['token'])
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $alice->email);

    expect($this->withToken($created['token'])->getJson('/api/v1/me')->json('data'))
        ->not->toHaveKey('password');

    $this->withToken($created['token'])
        ->getJson('/api/v1/orders/'.$aliceOrder->id)
        ->assertOk()
        ->assertJsonPath('data.number', $aliceOrder->number)
        ->assertJsonMissing(['storefront_token']);

    $this->withToken($created['token'])
        ->getJson('/api/v1/orders/'.$bobOrder->id)
        ->assertNotFound();
});

test('capability api routes are absent until the module is enabled', function () {
    $customer = Customer::factory()->create();
    Sanctum::actingAs($customer->user);

    $this->getJson('/api/v1/event-tickets')->assertNotFound();
    $this->getJson('/api/v1/subscriptions')->assertNotFound();
    $this->getJson('/api/v1/services')->assertNotFound();
    $this->getJson('/api/v1/downloads')->assertNotFound();
});

test('session cookie auth can read the current account on the api', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer->user)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $customer->email);
});

test('unauthenticated api errors are json and invoices are scoped to the owner', function () {
    $alice = Customer::factory()->create();
    $bob = Customer::factory()->create();
    $aliceInvoice = Invoice::query()->create([
        'number' => 'INV-API-ALICE',
        'status' => InvoiceStatus::Paid,
        'customer_id' => $alice->id,
        'customer_name' => $alice->name,
        'customer_email' => $alice->email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);
    $bobInvoice = Invoice::query()->create([
        'number' => 'INV-API-BOB',
        'status' => InvoiceStatus::Paid,
        'customer_id' => $bob->id,
        'customer_name' => $bob->name,
        'customer_email' => $bob->email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $unauthenticated = $this->getJson('/api/v1/orders')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
    expect((string) $unauthenticated->headers->get('content-type'))->toContain('application/json');

    Sanctum::actingAs($alice->user);

    $this->getJson('/api/v1/invoices/'.$aliceInvoice->id)
        ->assertOk()
        ->assertJsonPath('data.number', $aliceInvoice->number);

    $this->getJson('/api/v1/invoices/'.$bobInvoice->id)->assertNotFound();
});

test('api cart persists across requests with the cart token header', function () {
    $product = Product::factory()->active()->create([
        'name' => 'API cart lamp',
        'price_amount' => 1200,
    ]);

    $created = $this->postJson('/api/v1/cart', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    $token = $created->headers->get('X-Cart-Token');
    expect($token)->toBeString()->toHaveLength(64);

    $this->withHeader('X-Cart-Token', (string) $token)
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.lines.0.product_id', $product->id);
});

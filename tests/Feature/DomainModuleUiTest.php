<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('exposes an admin domain registration index to authorized staff', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff();
    $customer = Customer::factory()->create(['name' => 'Domain Owner']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);
    DomainRegistration::query()->create([
        'number' => 'DOM-ADMIN001',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'domain_name' => 'admin.example.test',
        'status' => 'pending',
    ]);

    $this->actingAs($staff)->get('/admin/domains')
        ->assertOk()
        ->assertSee('admin.example.test');
});

it('shows only the authenticated customer domains', function (): void {
    installAndEnableModules(['domains']);
    $customer = Customer::factory()->create(['name' => 'Domain Owner']);
    $other = Customer::factory()->create(['name' => 'Other Owner']);

    foreach ([[$customer, 'owned.example.test'], [$other, 'hidden.example.test']] as [$owner, $domain]) {
        $order = Order::factory()->create(['customer_id' => $owner->id]);
        $product = Product::factory()->active()->create(['name' => 'Domain registration']);
        DomainRegistration::query()->create([
            'number' => 'DOM-'.strtoupper(substr(md5($domain), 0, 8)),
            'order_id' => $order->id,
            'product_id' => $product->id,
            'customer_id' => $owner->id,
            'customer_email' => $owner->email,
            'domain_name' => $domain,
            'status' => 'active',
        ]);
    }

    $this->actingAs($customer->user)->get('/account/domains')
        ->assertOk()
        ->assertSee('owned.example.test')
        ->assertDontSee('hidden.example.test');
});

<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\Contracts\DomainRegistrar;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\Http\Livewire\Admin\RegistrationsIndex;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Livewire\Livewire;
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

it('lets authorized staff execute registrar and DNS actions from the domain screen', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $product = Product::factory()->active()->create();
    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-ACTION001',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'domain_name' => 'action.example.test',
        'registrar_key' => 'fixture-registrar',
        'dns_provider_key' => 'fixture-dns',
        'status' => 'pending',
    ]);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-registrar');
    $registrar->shouldReceive('capabilities')->andReturn(['registration']);
    $registrar->shouldReceive('register')->once()->andReturn([
        'provider_reference' => 'provider-action-1',
        'expires_at' => null,
        'status' => 'active',
        'meta' => [],
    ]);
    app(DomainRegistrarRegistry::class)->register($registrar);

    $dns = Mockery::mock(DomainDnsProvider::class);
    $dns->shouldReceive('key')->andReturn('fixture-dns');
    $dns->shouldReceive('capabilities')->andReturn(['zone_management']);
    $dns->shouldReceive('ensureZone')->once()->andReturn([
        'zone_reference' => 'zone-action-1',
        'nameservers' => [],
        'status' => 'active',
        'meta' => [],
    ]);
    app(DomainDnsProviderRegistry::class)->register($dns);

    $this->actingAs($staff);
    $component = Livewire::test(RegistrationsIndex::class)
        ->call('register', $registration->id)
        ->assertHasNoErrors();
    $component->call('ensureDnsZone', $registration->id)
        ->assertHasNoErrors();

    $updated = $registration->fresh();
    expect($updated?->status->value)->toBe('active')
        ->and($updated?->provider_reference)->toBe('provider-action-1')
        ->and($updated?->meta['dns_zone']['zone_reference'] ?? null)->toBe('zone-action-1');
});

it('denies domain operations to staff without domains.manage', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff(permissions: ['domains.view']);
    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-ACTION002',
        'domain_name' => 'forbidden.example.test',
        'registrar_key' => 'fixture-registrar',
        'status' => 'pending',
    ]);

    $this->actingAs($staff);
    Livewire::test(RegistrationsIndex::class)
        ->call('register', $registration->id)
        ->assertForbidden();
});

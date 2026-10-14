<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\Contracts\DomainRegistrar;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\Http\Livewire\Admin\RegistrationsIndex;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Admin\AdminRegistrar;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Lang;
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

it('keeps registrar and DNS management under one visible Domains tab', function (): void {
    installAndEnableModules(['domains']);

    $domainItems = collect(app(AdminRegistrar::class)->navigationItems())
        ->filter(fn ($item): bool => str_contains($item->id, 'domain'));

    expect($domainItems->pluck('id')->values()->all())->toBe(['domains'])
        ->and($domainItems->first()->href)->toBe('/admin/domains');
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

it('does not expose a guest domain through a matching email address', function (): void {
    installAndEnableModules(['domains']);
    $customer = Customer::factory()->create(['email' => 'shared@example.test']);
    $order = Order::factory()->create(['customer_id' => null, 'customer_email' => $customer->email]);
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);
    DomainRegistration::query()->create([
        'number' => 'DOM-GUEST001',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_id' => null,
        'customer_email' => $customer->email,
        'domain_name' => 'guest-owned.example.test',
        'status' => 'active',
    ]);

    $this->actingAs($customer->user)->get('/account/domains')
        ->assertOk()
        ->assertDontSee('guest-owned.example.test');
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

it('confirms registrar operations while view-only staff keep the domain facts without actions', function (string $locale): void {
    installAndEnableModules(['domains']);
    app()->setLocale($locale);

    foreach (['pending' => 'register.example.test', 'active' => 'renew.example.test'] as $status => $domain) {
        DomainRegistration::query()->create([
            'number' => 'DOM-'.strtoupper($status),
            'domain_name' => $domain,
            'status' => $status,
        ]);
    }

    $confirmRegister = Lang::has('domains::admin.register_confirm')
        ? __('domains::admin.register_confirm', ['domain' => 'register.example.test'])
        : __('common.confirm_external_action', ['action' => __('domains::admin.register'), 'name' => 'register.example.test']);
    $confirmRenew = Lang::has('domains::admin.renew_confirm')
        ? __('domains::admin.renew_confirm', ['domain' => 'renew.example.test'])
        : __('common.confirm_external_action', ['action' => __('domains::admin.renew'), 'name' => 'renew.example.test']);

    $manager = $this->createStaff();
    Livewire::actingAs($manager)
        ->test(RegistrationsIndex::class)
        ->assertSee('wire:confirm="'.$confirmRegister.'"', false)
        ->assertSee('wire:confirm="'.$confirmRenew.'"', false)
        ->assertDontSee('domains::admin.register_confirm', false)
        ->assertDontSee('domains::admin.renew_confirm', false);

    $viewer = $this->createStaff(permissions: ['domains.view']);
    Livewire::actingAs($viewer)
        ->test(RegistrationsIndex::class)
        ->assertSee('register.example.test')
        ->assertSee('renew.example.test')
        ->assertDontSee('wire:click="register(', false)
        ->assertDontSee('wire:click="renew(', false);
})->with(['en', 'nl']);

it('shows labelled domain status and provider facts on narrow screens without management actions for viewers', function (): void {
    installAndEnableModules(['domains']);
    $viewer = $this->createStaff(permissions: ['domains.view']);
    DomainRegistration::query()->create([
        'number' => 'DOM-MOBILE-001',
        'domain_name' => 'mobile.example.test',
        'registrar_key' => 'registrar-name',
        'status' => 'active',
    ]);

    Livewire::actingAs($viewer)
        ->test(RegistrationsIndex::class)
        ->assertSee('ag-table--mobile-cards', false)
        ->assertSee('data-label="'.__('domains::admin.status_label').'"', false)
        ->assertSee('ag-badge--success', false)
        ->assertSee('data-label="'.__('domains::admin.registrar').'"', false)
        ->assertSee('registrar-name')
        ->assertDontSee('wire:click="renew(', false);
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

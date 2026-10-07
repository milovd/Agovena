<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\Contracts\DomainRegistrar;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Livewire\Admin\Products\Edit;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('rejects unavailable domain providers before saving the capability', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.domain_registration', true)
        ->set('domainRegistrarKey', 'missing-registrar')
        ->set('domainDnsProviderKey', 'missing-dns-provider')
        ->call('saveCapabilities')
        ->assertHasErrors([
            'domainRegistrarKey' => __('admin.products.validation.domain_registrar_unavailable'),
            'domainDnsProviderKey' => __('admin.products.validation.domain_dns_provider_unavailable'),
        ]);

    expect($product->refresh()->capability('domain_registration'))->toBeNull();
});

it('rejects domain providers without the required capabilities', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-no-registration');
    $registrar->shouldReceive('capabilities')->andReturn(['availability_check']);
    app(DomainRegistrarRegistry::class)->register($registrar);

    $dnsProvider = Mockery::mock(DomainDnsProvider::class);
    $dnsProvider->shouldReceive('key')->andReturn('fixture-no-zone-management');
    $dnsProvider->shouldReceive('capabilities')->andReturn(['record_management']);
    app(DomainDnsProviderRegistry::class)->register($dnsProvider);

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.domain_registration', true)
        ->set('domainRegistrarKey', 'fixture-no-registration')
        ->set('domainDnsProviderKey', 'fixture-no-zone-management')
        ->call('saveCapabilities')
        ->assertHasErrors(['domainRegistrarKey', 'domainDnsProviderKey']);

    expect($product->refresh()->capability('domain_registration'))->toBeNull();
});

it('saves registration and DNS settings from the single Domain capability', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');
    installAndEnableExtension('cloudflare-domain');
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.domain_registration', true)
        ->set('domainRegistrarKey', 'namecheap-registrar')
        ->set('domainDnsProviderKey', 'cloudflare-dns')
        ->set('domainName', 'example.test')
        ->set('domainAutoRenew', true)
        ->set('domainYears', 2)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    $capability = $product->refresh()->capability('domain_registration');
    expect($capability?->config)->toMatchArray([
        'registrar_key' => 'namecheap-registrar',
        'dns_provider_key' => 'cloudflare-dns',
        'domain_name' => 'example.test',
        'auto_renew' => true,
        'years' => 2,
    ]);
});

it('normalizes invalid domain product settings to safe bounds', function (): void {
    installAndEnableModules(['domains']);
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['name' => 'Domain registration']);

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.domain_registration', true)
        ->set('domainName', '  Example.TEST.  ')
        ->set('domainYears', 0)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    $config = $product->refresh()->capability('domain_registration')?->config ?? [];
    expect($config['domain_name'] ?? null)->toBe('example.test')
        ->and($config['years'] ?? null)->toBe(1);
});

it('saves core capabilities without the optional domains module', function (): void {
    expect(app(ProductCapabilityRegistry::class)->has('domain_registration'))->toBeFalse();

    app(ProductCapabilityRegistry::class)->register(new ProductCapabilityDefinition(
        key: 'core_test_capability',
        label: 'Core test capability',
    ));
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['name' => 'Core product']);

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.core_test_capability', true)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    expect($product->refresh()->hasCapability('core_test_capability'))->toBeTrue();
});

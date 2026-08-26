<?php

declare(strict_types=1);

use App\Livewire\Admin\Products\Edit;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('saves independent registrar and DNS settings on a domain product', function (): void {
    installAndEnableModules(['domains']);
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

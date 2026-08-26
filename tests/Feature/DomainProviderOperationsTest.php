<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\Contracts\DomainRegistrar;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Models\DomainRegistration;

it('registers a pending domain and stores the registrar response', function (): void {
    installAndEnableModules(['domains']);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-registrar');
    $registrar->shouldReceive('capabilities')->andReturn(['registration']);
    $registrar->shouldReceive('register')->once()->andReturn([
        'provider_reference' => 'provider-registration-1',
        'expires_at' => '2027-08-26T00:00:00+00:00',
        'status' => 'active',
        'meta' => ['tld' => 'test'],
    ]);
    app(DomainRegistrarRegistry::class)->register($registrar);

    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-REGISTER001',
        'domain_name' => 'example.test',
        'registrar_key' => 'fixture-registrar',
        'provider_key' => 'fixture-registrar',
        'status' => 'pending',
        'meta' => ['existing' => 'value'],
    ]);

    $result = app(DomainService::class)->register($registration);

    expect($result->fresh()->status->value)->toBe('active')
        ->and($result->provider_reference)->toBe('provider-registration-1')
        ->and($result->expires_at?->toIso8601String())->toBe('2027-08-26T00:00:00+00:00')
        ->and($result->registered_at)->not->toBeNull()
        ->and($result->failure_message)->toBeNull()
        ->and($result->meta['existing'] ?? null)->toBe('value')
        ->and($result->meta['provider_response']['tld'] ?? null)->toBe('test');
});

it('marks a domain registration failed when the registrar rejects it', function (): void {
    installAndEnableModules(['domains']);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-registrar');
    $registrar->shouldReceive('capabilities')->andReturn(['registration']);
    $registrar->shouldReceive('register')->once()->andThrow(new RuntimeException('provider rejected request'));
    app(DomainRegistrarRegistry::class)->register($registrar);

    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-REGISTER002',
        'domain_name' => 'example.test',
        'registrar_key' => 'fixture-registrar',
        'status' => 'pending',
    ]);

    expect(fn (): DomainRegistration => app(DomainService::class)->register($registration))
        ->toThrow(RuntimeException::class);

    $failed = $registration->fresh();
    expect($failed?->status->value)->toBe('failed')
        ->and($failed?->failure_message)->toBe('provider rejected request')
        ->and($failed?->failed_at)->not->toBeNull();
});

it('renews an active domain through the configured registrar', function (): void {
    installAndEnableModules(['domains']);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-registrar');
    $registrar->shouldReceive('capabilities')->andReturn(['renewal']);
    $registrar->shouldReceive('renew')->withArgs(function (DomainRegistration $domain, int $years): bool {
        return $domain->domain_name === 'example.test' && $years === 2;
    })->once()->andReturn([
        'provider_reference' => 'provider-renewal-1',
        'expires_at' => '2028-08-26T00:00:00+00:00',
        'status' => 'active',
        'meta' => ['years' => 2],
    ]);
    app(DomainRegistrarRegistry::class)->register($registrar);

    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-RENEW001',
        'domain_name' => 'example.test',
        'registrar_key' => 'fixture-registrar',
        'status' => 'active',
        'meta' => [],
    ]);

    $result = app(DomainService::class)->renew($registration, 2);

    expect($result->fresh()->status->value)->toBe('active')
        ->and($result->provider_reference)->toBe('provider-renewal-1')
        ->and($result->expires_at?->toIso8601String())->toBe('2028-08-26T00:00:00+00:00')
        ->and($result->meta['provider_response']['years'] ?? null)->toBe(2);
});

it('stores DNS zone state independently from registrar state', function (): void {
    installAndEnableModules(['domains']);

    $dns = Mockery::mock(DomainDnsProvider::class);
    $dns->shouldReceive('key')->andReturn('fixture-dns');
    $dns->shouldReceive('capabilities')->andReturn(['zone_management', 'record_management']);
    $dns->shouldReceive('ensureZone')->once()->andReturn([
        'zone_reference' => 'zone-1',
        'nameservers' => ['ns1.fixture.test', 'ns2.fixture.test'],
        'status' => 'active',
        'meta' => ['provider' => 'fixture'],
    ]);
    app(DomainDnsProviderRegistry::class)->register($dns);

    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-DNS001',
        'domain_name' => 'example.test',
        'registrar_key' => 'namecheap-registrar',
        'dns_provider_key' => 'fixture-dns',
        'status' => 'pending',
        'meta' => ['registrar_key' => 'namecheap-registrar'],
    ]);

    $result = app(DomainService::class)->ensureDnsZone($registration);

    expect($result->fresh()->registrar_key)->toBe('namecheap-registrar')
        ->and($result->dns_provider_key)->toBe('fixture-dns')
        ->and($result->meta['dns_zone']['zone_reference'] ?? null)->toBe('zone-1')
        ->and($result->meta['dns_zone']['nameservers'] ?? [])->toBe(['ns1.fixture.test', 'ns2.fixture.test']);
});

it('rejects DNS and registrar operations when the provider capability is missing', function (): void {
    installAndEnableModules(['domains']);

    $registrar = Mockery::mock(DomainRegistrar::class);
    $registrar->shouldReceive('key')->andReturn('fixture-registrar');
    $registrar->shouldReceive('capabilities')->andReturn([]);
    app(DomainRegistrarRegistry::class)->register($registrar);

    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-CAP001',
        'domain_name' => 'example.test',
        'registrar_key' => 'fixture-registrar',
        'status' => 'pending',
    ]);

    expect(fn (): DomainRegistration => app(DomainService::class)->register($registration))
        ->toThrow(RuntimeException::class, 'does not support registration');
});

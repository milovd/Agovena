<?php

declare(strict_types=1);

use Agovena\Extensions\CloudflareDns\CloudflareDnsApi;
use Agovena\Extensions\CloudflareDns\CloudflareDnsProvider;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionManager;
use Illuminate\Support\Facades\File;

it('discovers and registers the Cloudflare DNS extension separately from the registrar', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $manifest = collect(app(ExtensionManager::class)->discover())->firstWhere('id', 'cloudflare-dns');

    expect(File::exists($root.'/extensions/domains/cloudflare-dns/extension.json'))->toBeTrue()
        ->and($manifest)->not->toBeNull()
        ->and($manifest->category->value)->toBe('domain_dns_provider')
        ->and($manifest->moduleDependencies)->toContain('domains');

    installAndEnableModules(['domains']);
    installAndEnableExtension('cloudflare-dns');

    expect(app(DomainDnsProviderRegistry::class)->get('cloudflare-dns'))->toBeInstanceOf(CloudflareDnsProvider::class);
});

it('ensures a zone and maps Cloudflare DNS records through the provider contract', function (): void {
    installAndEnableModules(['domains']);

    $api = Mockery::mock(CloudflareDnsApi::class);
    $api->shouldReceive('findOrCreateZone')->once()->with('example.test')->andReturn([
        'id' => 'zone-1',
        'name' => 'example.test',
        'status' => 'active',
        'name_servers' => ['ns1.example.test', 'ns2.example.test'],
    ]);
    $api->shouldReceive('listRecords')->once()->with('zone-1')->andReturn([
        ['id' => 'record-1', 'type' => 'A', 'name' => 'example.test', 'content' => '203.0.113.10'],
    ]);
    $api->shouldReceive('createRecord')->once()->with('zone-1', [
        'type' => 'TXT',
        'name' => 'example.test',
        'content' => 'agovena-verification',
        'ttl' => 3600,
        'proxied' => false,
    ])->andReturn(['id' => 'record-2']);
    $api->shouldReceive('deleteRecord')->once()->with('zone-1', 'record-2')->andReturn(['id' => 'record-2']);

    $provider = new CloudflareDnsProvider($api);
    $registration = new DomainRegistration([
        'domain_name' => 'example.test',
        'meta' => [],
    ]);

    $zone = $provider->ensureZone($registration);
    $registration->setAttribute('meta', [
        'dns_zone' => ['zone_reference' => 'zone-1'],
    ]);
    $records = $provider->listRecords($registration);
    $created = $provider->upsertRecord($registration, [
        'type' => 'TXT',
        'name' => 'example.test',
        'content' => 'agovena-verification',
        'ttl' => 3600,
        'proxied' => false,
    ]);
    $deleted = $provider->deleteRecord($registration, 'record-2');

    expect($provider->key())->toBe('cloudflare-dns')
        ->and($provider->capabilities())->toBe(['zone_management', 'record_management'])
        ->and($zone['zone_reference'])->toBe('zone-1')
        ->and($zone['nameservers'])->toBe(['ns1.example.test', 'ns2.example.test'])
        ->and($records[0]['id'] ?? null)->toBe('record-1')
        ->and($created['id'] ?? null)->toBe('record-2')
        ->and($deleted['id'] ?? null)->toBe('record-2');
});

<?php

declare(strict_types=1);

use Agovena\Extensions\DomainDns\CloudflareApi;
use Agovena\Extensions\DomainDns\CloudflareDnsApi;
use Agovena\Extensions\DomainDns\CloudflareDnsProvider;
use Agovena\Extensions\DomainDns\CloudflareRegistrar;
use Agovena\Extensions\DomainDns\NamecheapApi;
use Agovena\Extensions\DomainDns\NamecheapRegistrar;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManager;
use App\Livewire\Admin\Extensions\Index;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('migrates legacy domain provider installs and settings into the unified package', function (): void {
    $legacyPackagePath = storage_path('app/packages/extensions/cloudflare-dns');
    File::ensureDirectoryExists($legacyPackagePath);
    File::put($legacyPackagePath.'/legacy.marker', 'legacy package');

    DB::table('agovena_extensions')->insert([
        'extension_id' => 'cloudflare-dns',
        'version' => '1.0.0',
        'enabled' => true,
        'installed_at' => now(),
        'enabled_at' => now(),
        'disabled_at' => null,
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('agovena_packages')->insert([
        'kind' => 'extension',
        'agovena_id' => 'cloudflare-dns',
        'composer_name' => 'domains/cloudflare-dns',
        'source_type' => 'path',
        'source_locator' => 'extensions/domains/cloudflare-dns',
        'version_constraint' => '*',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => 'storage/app/packages/extensions/cloudflare-dns',
        'is_bundled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('extension_settings')->insert([
        'extension_id' => 'cloudflare-dns',
        'key' => 'account_id',
        'value' => 'fixture-account',
        'is_secret' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $migration = require base_path('database/migrations/2026_08_28_120000_merge_domain_provider_extensions.php');
        $migration->up();
        $migration->up();

        $packagePath = (string) DB::table('agovena_packages')->where('agovena_id', 'domain-dns')->value('install_path');
        expect(DB::table('agovena_extensions')->where('extension_id', 'domain-dns')->value('enabled'))->toBe(1)
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeFalse()
            ->and(DB::table('agovena_packages')->where('agovena_id', 'domain-dns')->value('composer_name'))->toBe('domain-dns')
            ->and($packagePath)->toContain('domain-dns')
            ->and(File::exists($packagePath.'/extension.json'))->toBeTrue()
            ->and(File::exists($legacyPackagePath))->toBeFalse()
            ->and(DB::table('agovena_packages')->where('agovena_id', 'domain-dns')->count())->toBe(1)
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-dns')->exists())->toBeFalse()
            ->and(DB::table('extension_settings')->where('extension_id', 'domain-dns')->where('key', 'cloudflare_account_id')->value('value'))->toBe('fixture-account')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-dns')->exists())->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/domain-dns'));
        File::deleteDirectory($legacyPackagePath);
    }
});

it('fails closed when the unified package is unavailable during a legacy upgrade', function (): void {
    DB::table('agovena_extensions')->insert([
        'extension_id' => 'namecheap-registrar',
        'version' => '1.0.0',
        'enabled' => true,
        'installed_at' => now(),
        'enabled_at' => now(),
        'disabled_at' => null,
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('agovena_packages')->insert([
        'kind' => 'extension',
        'agovena_id' => 'namecheap-registrar',
        'composer_name' => 'domains/namecheap-registrar',
        'source_type' => 'path',
        'source_locator' => 'extensions/domains/namecheap-registrar',
        'version_constraint' => '*',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => 'storage/app/packages/extensions/namecheap-registrar',
        'is_bundled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('extension_settings')->insert([
        'extension_id' => 'namecheap-registrar',
        'key' => 'api_key',
        'value' => '[REDACTED]',
        'is_secret' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $configuredPath = config('agovena.packages.optional_packages_path');
    config(['agovena.packages.optional_packages_path' => base_path('missing-domain-dns-package')]);

    try {
        $migration = require base_path('database/migrations/2026_08_28_120000_merge_domain_provider_extensions.php');

        expect(fn (): mixed => $migration->up())->toThrow(RuntimeException::class);
    } finally {
        config(['agovena.packages.optional_packages_path' => $configuredPath]);
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'namecheap-registrar')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'domain-dns')->exists())->toBeFalse()
        ->and(DB::table('agovena_packages')->where('agovena_id', 'namecheap-registrar')->exists())->toBeTrue()
        ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-registrar')->where('key', 'api_key')->value('value'))->toBe('[REDACTED]');
});

it('fails closed for an incomplete unified manifest', function (): void {
    DB::table('agovena_extensions')->insert([
        'extension_id' => 'cloudflare-dns',
        'version' => '1.0.0',
        'enabled' => true,
        'installed_at' => now(),
        'enabled_at' => now(),
        'disabled_at' => null,
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $root = storage_path('framework/testing/incomplete-domain-dns');
    $packagePath = $root.'/extensions/domains/domain-dns';
    File::ensureDirectoryExists($packagePath);
    File::put($packagePath.'/extension.json', json_encode(['id' => 'domain-dns']));
    $configuredPath = config('agovena.packages.optional_packages_path');
    config(['agovena.packages.optional_packages_path' => $root]);

    try {
        $migration = require base_path('database/migrations/2026_08_28_120000_merge_domain_provider_extensions.php');

        expect(fn (): mixed => $migration->up())->toThrow(RuntimeException::class);
    } finally {
        config(['agovena.packages.optional_packages_path' => $configuredPath]);
        File::deleteDirectory($root);
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'domain-dns')->exists())->toBeFalse();
});

it('exposes domain registration and DNS as one installable package', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');
    $manifest = collect(app(ExtensionManager::class)->discover())->firstWhere('id', 'domain-dns');

    expect($catalog['domain-dns'] ?? null)->toBe([
        'kind' => 'extension',
        'path' => 'extensions/domains/domain-dns',
    ])
        ->and(File::exists($root.'/extensions/domains/domain-dns/extension.json'))->toBeTrue()
        ->and($manifest)->not->toBeNull()
        ->and($manifest->name)->toBe('Domain DNS')
        ->and($manifest->category)->toBe(ExtensionCategory::Domain)
        ->and($manifest->moduleDependencies)->toContain('domains')
        ->and(collect(ExtensionCategory::cases())->pluck('value')->all())->not->toContain('domain_registrar')
        ->and(collect(ExtensionCategory::cases())->pluck('value')->all())->not->toContain('domain_dns_provider')
        ->and($catalog)->not->toHaveKeys(['cloudflare-dns', 'cloudflare-registrar', 'namecheap-registrar']);
});

it('renders one Domain card in the extension catalog', function (): void {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->set('tab', 'available')
        ->assertSee('Domain DNS')
        ->assertDontSee('Cloudflare Registrar')
        ->assertDontSee('Namecheap Registrar');
});

it('registers Cloudflare DNS and registrar providers behind the same extension', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('domain-dns');

    expect(app(DomainDnsProviderRegistry::class)->get('cloudflare-dns'))->toBeInstanceOf(CloudflareDnsProvider::class)
        ->and(app(DomainRegistrarRegistry::class)->get('cloudflare-registrar'))->toBeInstanceOf(CloudflareRegistrar::class)
        ->and(app(DomainRegistrarRegistry::class)->get('namecheap-registrar'))->toBeInstanceOf(NamecheapRegistrar::class);
});

it('ensures Cloudflare DNS zones and maps records through the integrated provider', function (): void {
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
    $registration->setAttribute('meta', ['dns_zone' => ['zone_reference' => 'zone-1']]);
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

it('maps Cloudflare registration capabilities through the integrated provider', function (): void {
    installAndEnableModules(['domains']);

    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('check')->once()->with(['acmecorp.dev'])->andReturn([
        'domains' => [[
            'name' => 'acmecorp.dev',
            'registrable' => true,
            'pricing' => ['registration_cost' => '10.11', 'currency' => 'USD'],
        ]],
    ]);
    $api->shouldReceive('register')->once()->with('acmecorp.dev', ['auto_renew' => true])->andReturn([
        'id' => 'registration-123',
        'domain_name' => 'acmecorp.dev',
        'status' => 'pending',
        'expires_at' => null,
    ]);
    $registrar = new CloudflareRegistrar($api);
    $registration = new DomainRegistration(['domain_name' => 'acmecorp.dev', 'auto_renew' => true]);

    expect($registrar->checkAvailability('AcmeCorp.dev'))->toMatchArray([
        'available' => true,
        'domain' => 'acmecorp.dev',
        'price_minor' => 1011,
        'currency' => 'USD',
    ])
        ->and($registrar->register($registration))->toMatchArray([
            'provider_reference' => 'registration-123',
            'status' => 'pending',
        ]);
});

it('maps Namecheap registration and renewal capabilities through the integrated provider', function (): void {
    installAndEnableModules(['domains']);

    $api = Mockery::mock(NamecheapApi::class);
    $api->shouldReceive('check')->once()->with(['example.test'])->andReturn([
        'domains' => [[
            'domain' => 'example.test',
            'available' => true,
            'registration_price' => '12.50',
            'currency' => 'USD',
        ]],
    ]);
    $api->shouldReceive('register')->once()->with('example.test', 2)->andReturn([
        'domain' => 'example.test',
        'registered' => true,
        'domain_id' => '123',
        'order_id' => '456',
        'transaction_id' => '789',
        'charged_amount' => '25.00',
    ]);
    $api->shouldReceive('renew')->once()->with('example.test', 1)->andReturn([
        'domain' => 'example.test',
        'renewed' => true,
        'domain_id' => '123',
        'order_id' => '457',
        'transaction_id' => '790',
        'charged_amount' => '12.50',
    ]);
    $registrar = new NamecheapRegistrar($api);
    $registration = new DomainRegistration([
        'domain_name' => 'Example.test',
        'meta' => ['provider_settings' => ['years' => 2]],
    ]);

    expect($registrar->checkAvailability('Example.test'))->toMatchArray([
        'available' => true,
        'domain' => 'example.test',
        'price_minor' => 1250,
        'currency' => 'USD',
    ])
        ->and($registrar->register($registration))->toMatchArray([
            'provider_reference' => '789',
            'status' => 'active',
        ])
        ->and($registrar->renew($registration))->toMatchArray([
            'provider_reference' => '790',
            'status' => 'active',
        ]);
});

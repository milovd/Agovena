<?php

declare(strict_types=1);

use Agovena\Extensions\CloudflareDomain\CloudflareApi;
use Agovena\Extensions\CloudflareDomain\CloudflareDnsApi;
use Agovena\Extensions\CloudflareDomain\CloudflareDnsProvider;
use Agovena\Extensions\CloudflareDomain\CloudflareRegistrar;
use Agovena\Extensions\NamecheapDomain\NamecheapApi;
use Agovena\Extensions\NamecheapDomain\NamecheapRegistrar;
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

function runDomainProviderMigration(): void
{
    $migration = require base_path('database/migrations/2026_08_28_120000_merge_domain_provider_extensions.php');
    $migration->up();
}

function insertLegacyDomainExtension(string $id, bool $enabled = true): void
{
    DB::table('agovena_extensions')->insert([
        'extension_id' => $id,
        'version' => '1.0.0',
        'enabled' => $enabled,
        'installed_at' => now(),
        'enabled_at' => $enabled ? now() : null,
        'disabled_at' => $enabled ? null : now(),
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertLegacyDomainPackage(string $id, string $sourceType = 'path', ?string $sourceLocator = null): string
{
    $path = storage_path('app/packages/extensions/'.$id);
    File::ensureDirectoryExists($path);
    File::put($path.'/legacy.marker', $id);

    DB::table('agovena_packages')->insert([
        'kind' => 'extension',
        'agovena_id' => $id,
        'composer_name' => 'domains/'.$id,
        'source_type' => $sourceType,
        'source_locator' => $sourceLocator ?? 'extensions/domains/'.$id,
        'version_constraint' => '*',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => $path,
        'is_bundled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $path;
}

function insertLegacyDomainSetting(string $extensionId, string $key, mixed $value, bool $secret = false): void
{
    DB::table('extension_settings')->insert([
        'extension_id' => $extensionId,
        'key' => $key,
        'value' => $value,
        'is_secret' => $secret,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('migrates Cloudflare DNS and registrar into one Cloudflare extension', function (): void {
    $legacyPaths = [];
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainExtension('cloudflare-registrar');
    $legacyPaths[] = insertLegacyDomainPackage('cloudflare-dns');
    $legacyPaths[] = insertLegacyDomainPackage('cloudflare-registrar');
    insertLegacyDomainSetting('cloudflare-dns', 'account_id', 'fixture-account');
    insertLegacyDomainSetting('cloudflare-dns', 'api_token', '[REDACTED]', true);
    insertLegacyDomainSetting('cloudflare-registrar', 'account_id', 'fixture-account');
    insertLegacyDomainSetting('cloudflare-registrar', 'api_token', '[REDACTED]', true);

    try {
        runDomainProviderMigration();
        runDomainProviderMigration();

        expect(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->value('enabled'))->toBe(1)
            ->and(DB::table('agovena_extensions')->whereIn('extension_id', ['cloudflare-dns', 'cloudflare-registrar'])->exists())->toBeFalse()
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->value('composer_name'))->toBe('cloudflare-domain')
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->value('source_type'))->toBe('path')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'account_id')->value('value'))->toBe('fixture-account')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'api_token')->value('value'))->toBe('[REDACTED]')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'api_token')->value('is_secret'))->toBe(1)
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->count())->toBe(1);
        foreach ($legacyPaths as $path) {
            expect(File::exists($path))->toBeFalse();
        }
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        foreach ($legacyPaths as $path) {
            File::deleteDirectory($path);
        }
    }
});

it('migrates Namecheap into a separate Namecheap extension', function (): void {
    $legacyPath = insertLegacyDomainPackage('namecheap-registrar');
    insertLegacyDomainExtension('namecheap-registrar');
    insertLegacyDomainSetting('namecheap-registrar', 'api_user', 'fixture-user');
    insertLegacyDomainSetting('namecheap-registrar', 'api_key', '[REDACTED]', true);
    insertLegacyDomainSetting('namecheap-registrar', 'username', 'fixture-user');
    insertLegacyDomainSetting('namecheap-registrar', 'client_ip', '198.51.100.10');
    insertLegacyDomainSetting('namecheap-registrar', 'sandbox', true);

    try {
        runDomainProviderMigration();

        expect(DB::table('agovena_extensions')->where('extension_id', 'namecheap-domain')->value('enabled'))->toBe(1)
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse()
            ->and(DB::table('agovena_packages')->where('agovena_id', 'namecheap-domain')->value('composer_name'))->toBe('namecheap-domain')
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'api_key')->value('value'))->toBe('[REDACTED]')
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'api_key')->value('is_secret'))->toBe(1)
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'sandbox')->value('value'))->toBe('1')
            ->and(File::exists($legacyPath))->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory($legacyPath);
    }
});

it('fails closed before database mutation when a target package is unavailable', function (): void {
    insertLegacyDomainExtension('namecheap-registrar');
    insertLegacyDomainPackage('namecheap-registrar');
    insertLegacyDomainSetting('namecheap-registrar', 'api_key', '[REDACTED]', true);
    $configuredPath = config('agovena.packages.optional_packages_path');
    config(['agovena.packages.optional_packages_path' => base_path('missing-domain-provider-package')]);

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class);
    } finally {
        config(['agovena.packages.optional_packages_path' => $configuredPath]);
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-registrar'));
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'namecheap-registrar')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'namecheap-domain')->exists())->toBeFalse()
        ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-registrar')->where('key', 'api_key')->value('value'))->toBe('[REDACTED]');
});

it('fails closed for malformed source locators and unsupported legacy settings', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns', 'monorepo', '');
    insertLegacyDomainSetting('cloudflare-dns', 'unknown', 'value');

    expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();

    File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
    File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-dns'));
});

it('fails closed for a missing required runtime file', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns');
    $configuredRoot = config('agovena.packages.optional_packages_path');
    $root = storage_path('framework/testing/missing-cloudflare-runtime-file');
    $source = $root.'/extensions/domains/cloudflare-domain';
    File::copyDirectory($configuredRoot.'/extensions/domains/cloudflare-domain', $source);
    File::delete($source.'/lang/en/messages.php');
    config(['agovena.packages.optional_packages_path' => $root]);

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class);
    } finally {
        config(['agovena.packages.optional_packages_path' => $configuredRoot]);
        File::deleteDirectory($root);
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-dns'));
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();
});

it('fails closed when an existing target setting conflicts with legacy data', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainSetting('cloudflare-dns', 'account_id', 'legacy-account');
    DB::table('agovena_extensions')->insert([
        'extension_id' => 'cloudflare-domain',
        'version' => '1.0.0',
        'enabled' => false,
        'installed_at' => now(),
        'enabled_at' => null,
        'disabled_at' => now(),
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    insertLegacyDomainSetting('cloudflare-domain', 'account_id', 'existing-account');

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class);
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-dns'));
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'account_id')->value('value'))->toBe('existing-account');
});

it('exposes two provider-specific domain packages in one Domain category', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');
    $manifests = collect(app(ExtensionManager::class)->discover());

    expect($catalog['cloudflare-domain'] ?? null)->toBe([
        'kind' => 'extension',
        'path' => 'extensions/domains/cloudflare-domain',
    ])
        ->and($catalog['namecheap-domain'] ?? null)->toBe([
            'kind' => 'extension',
            'path' => 'extensions/domains/namecheap-domain',
        ])
        ->and($catalog)->not->toHaveKeys(['domain-dns', 'cloudflare-dns', 'cloudflare-registrar', 'namecheap-registrar'])
        ->and(File::exists($root.'/extensions/domains/cloudflare-domain/extension.json'))->toBeTrue()
        ->and(File::exists($root.'/extensions/domains/namecheap-domain/extension.json'))->toBeTrue()
        ->and($manifests->whereIn('id', ['cloudflare-domain', 'namecheap-domain'])->count())->toBe(2)
        ->and($manifests->whereIn('category', [ExtensionCategory::Domain])->count())->toBe(2)
        ->and(collect(ExtensionCategory::cases())->pluck('value')->all())->not->toContain('domain_registrar')
        ->and(collect(ExtensionCategory::cases())->pluck('value')->all())->not->toContain('domain_dns_provider');
});

it('renders both provider extensions in the Domain catalog surface', function (): void {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->set('tab', 'available')
        ->assertSee('Cloudflare Domains')
        ->assertSee('Namecheap Domains')
        ->assertDontSee('Cloudflare Registrar')
        ->assertDontSee('Namecheap Registrar')
        ->assertDontSee('Domain DNS');
});

it('registers Cloudflare registration and DNS under one extension', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('cloudflare-domain');

    expect(app(DomainDnsProviderRegistry::class)->get('cloudflare-dns'))->toBeInstanceOf(CloudflareDnsProvider::class)
        ->and(app(DomainRegistrarRegistry::class)->get('cloudflare-registrar'))->toBeInstanceOf(CloudflareRegistrar::class)
        ->and(app(DomainRegistrarRegistry::class)->get('namecheap-registrar'))->toBeNull();
});

it('registers Namecheap registration and renewal under its separate extension', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');

    expect(app(DomainRegistrarRegistry::class)->get('namecheap-registrar'))->toBeInstanceOf(NamecheapRegistrar::class)
        ->and(app(DomainDnsProviderRegistry::class)->get('cloudflare-dns'))->toBeNull();
});

it('maps Cloudflare DNS zone and record management', function (): void {
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
    $registration = new DomainRegistration(['domain_name' => 'example.test', 'meta' => []]);
    $zone = $provider->ensureZone($registration);
    $registration->setAttribute('meta', ['dns_zone' => ['zone_reference' => 'zone-1']]);

    expect($provider->key())->toBe('cloudflare-dns')
        ->and($provider->capabilities())->toBe(['zone_management', 'record_management'])
        ->and($zone['zone_reference'])->toBe('zone-1')
        ->and($provider->listRecords($registration)[0]['id'])->toBe('record-1')
        ->and($provider->upsertRecord($registration, [
            'type' => 'TXT',
            'name' => 'example.test',
            'content' => 'agovena-verification',
            'ttl' => 3600,
            'proxied' => false,
        ])['id'])->toBe('record-2')
        ->and($provider->deleteRecord($registration, 'record-2')['id'])->toBe('record-2');
});

it('maps Cloudflare registration capabilities', function (): void {
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
    ])->and($registrar->register($registration))->toMatchArray([
        'provider_reference' => 'registration-123',
        'status' => 'pending',
    ]);
});

it('maps Namecheap registration and renewal capabilities', function (): void {
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
    ])->and($registrar->register($registration))->toMatchArray([
        'provider_reference' => '789',
        'status' => 'active',
    ])->and($registrar->renew($registration))->toMatchArray([
        'provider_reference' => '790',
        'status' => 'active',
    ]);
});

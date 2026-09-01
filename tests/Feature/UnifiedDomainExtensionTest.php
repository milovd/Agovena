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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    foreach (['cloudflare-domain', 'namecheap-domain', '.cloudflare-domain.staging', '.cloudflare-domain.backup', '.namecheap-domain.staging', '.namecheap-domain.backup'] as $directory) {
        File::deleteDirectory(storage_path('app/packages/extensions/'.$directory));
    }
    foreach (['.cloudflare-domain.materialization.json', '.namecheap-domain.materialization.json'] as $journal) {
        File::delete(storage_path('app/packages/extensions/'.$journal));
    }
});

function runDomainProviderMigration(): void
{
    $migration = require base_path('database/migrations/2026_08_28_115500_split_domain_provider_extensions.php');
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

it('fails closed on a tampered materialization journal before mutation', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns');
    $journal = storage_path('app/packages/extensions/.cloudflare-domain.materialization.json');
    File::ensureDirectoryExists(dirname($journal));
    File::put($journal, json_encode([
        'target_id' => 'cloudflare-domain',
        'destination' => storage_path('app/packages/extensions/other-target'),
        'staging' => storage_path('app/packages/extensions/.other-target.staging'),
        'backup' => storage_path('app/packages/extensions/.other-target.backup'),
        'phase' => 'activated',
        'destination_existed' => false,
    ], JSON_THROW_ON_ERROR));

    expect(fn () => runDomainProviderMigration())->toThrow(RuntimeException::class)
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();
    File::delete($journal);
});

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
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->value('composer_name'))->toBeNull()
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->value('source_type'))->toBe('path')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'account_id')->value('value'))->toBe('fixture-account')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'api_token')->value('value'))->toBe('[REDACTED]')
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'api_token')->value('is_secret'))->toBe(1)
            ->and(DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->count())->toBe(1);
        foreach ($legacyPaths as $path) {
            expect(File::exists($path))->toBeFalse();
        }
        expect(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.staging')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.backup')))->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        foreach ($legacyPaths as $path) {
            File::deleteDirectory($path);
        }
    }
});

it('normalizes materialized package sources and preserves legacy provenance metadata', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainExtension('cloudflare-registrar', false);
    $composerPath = insertLegacyDomainPackage('cloudflare-dns', 'composer', 'vendor/cloudflare-domain');
    $vcsPath = insertLegacyDomainPackage('cloudflare-registrar', 'vcs', 'https://legacy-user:legacy-pass@example.test/agovena/cloudflare-domain?token=fixture-token&branch=main');
    DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->update([
        'meta' => json_encode([
            'legacy_dns' => ['enabled_by' => 'fixture'],
            'api_token' => 'fixture-token',
            'nested' => ['authorization' => 'Bearer fixture-token', 'safe' => 'fixture'],
        ]),
    ]);
    DB::table('agovena_extensions')->where('extension_id', 'cloudflare-registrar')->update([
        'meta' => json_encode(['legacy_registrar' => ['source' => 'fixture']]),
    ]);

    try {
        runDomainProviderMigration();

        $package = DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->firstOrFail();
        $meta = json_decode((string) DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->value('meta'), true, 512, JSON_THROW_ON_ERROR);
        $sources = $meta['_agovena_migration']['legacy_package_sources'] ?? [];

        expect($package->composer_name)->toBeNull()
            ->and($package->source_type)->toBe('path')
            ->and($package->source_locator)->toBe(realpath(storage_path('app/packages/extensions/cloudflare-domain')))
            ->and($meta['legacy_dns']['enabled_by'])->toBe('fixture')
            ->and($meta['api_token'])->toBe('[REDACTED]')
            ->and($meta['nested']['authorization'])->toBe('[REDACTED]')
            ->and($meta['nested']['safe'])->toBe('fixture')
            ->and($meta['legacy_registrar']['source'])->toBe('fixture')
            ->and($sources)->toHaveCount(2)
            ->and(collect($sources)->pluck('source_type')->all())->toContain('composer', 'vcs')
            ->and(collect($sources)->firstWhere('source_type', 'vcs')['source_locator'])
            ->toBe('https://example.test/agovena/cloudflare-domain?branch=main');
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($composerPath);
        File::deleteDirectory($vcsPath);
    }
});

it('preserves an existing target package source before normalizing it', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $legacyPath = insertLegacyDomainPackage('cloudflare-dns');
    $targetPath = storage_path('app/packages/extensions/cloudflare-domain');
    DB::table('agovena_packages')->insert([
        'kind' => 'extension',
        'agovena_id' => 'cloudflare-domain',
        'composer_name' => 'vendor/existing-cloudflare',
        'source_type' => 'composer',
        'source_locator' => 'vendor/existing-cloudflare',
        'version_constraint' => '^2.0',
        'installed_version' => '2.1.0',
        'available_version' => '2.2.0',
        'install_path' => $targetPath,
        'is_bundled' => false,
        'created_at' => '2023-01-02 03:04:05',
        'updated_at' => '2023-02-03 04:05:06',
    ]);

    try {
        runDomainProviderMigration();
        $meta = json_decode((string) DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->value('meta'), true, 512, JSON_THROW_ON_ERROR);

        expect($meta['_agovena_migration']['target_package_source']['composer_name'])->toBe('vendor/existing-cloudflare')
            ->and($meta['_agovena_migration']['target_package_source']['source_type'])->toBe('composer')
            ->and($meta['_agovena_migration']['target_package_source']['source_locator'])->toBe('vendor/existing-cloudflare')
            ->and($meta['_agovena_migration']['target_package_source']['install_path'])->toBe($targetPath)
            ->and($meta['_agovena_migration']['target_package_source']['created_at'])->toBe('2023-01-02 03:04:05')
            ->and($meta['_agovena_migration']['target_package_source']['updated_at'])->toBe('2023-02-03 04:05:06');
    } finally {
        File::deleteDirectory($targetPath);
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
});

it('redacts credentials from preserved package source provenance', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $legacyPath = insertLegacyDomainPackage('cloudflare-dns');
    $targetPath = storage_path('app/packages/extensions/cloudflare-domain');
    DB::table('agovena_packages')->insert([
        'kind' => 'extension',
        'agovena_id' => 'cloudflare-domain',
        'composer_name' => 'vendor/existing-cloudflare',
        'source_type' => 'vcs',
        'source_locator' => 'https://user:secret@example.test/repo?token=abc123&ref=main',
        'version_constraint' => '^2.0',
        'installed_version' => '2.1.0',
        'available_version' => '2.2.0',
        'install_path' => $targetPath,
        'is_bundled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        runDomainProviderMigration();
        $meta = json_decode((string) DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->value('meta'), true, 512, JSON_THROW_ON_ERROR);
        $source = $meta['_agovena_migration']['target_package_source']['source_locator'];

        expect($source)->toBe('https://example.test/repo?ref=main')
            ->and($source)->not->toContain('secret')
            ->and($source)->not->toContain('abc123');
    } finally {
        File::deleteDirectory($targetPath);
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
});

it('selects the earliest timestamp for identical migrated settings', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainExtension('cloudflare-registrar');
    $dnsPath = insertLegacyDomainPackage('cloudflare-dns');
    $registrarPath = insertLegacyDomainPackage('cloudflare-registrar');
    insertLegacyDomainSetting('cloudflare-dns', 'account_id', 'fixture-account');
    insertLegacyDomainSetting('cloudflare-registrar', 'account_id', 'fixture-account');
    DB::table('extension_settings')->where('extension_id', 'cloudflare-dns')->where('key', 'account_id')->update([
        'created_at' => '2022-01-02 03:04:05',
    ]);
    DB::table('extension_settings')->where('extension_id', 'cloudflare-registrar')->where('key', 'account_id')->update([
        'created_at' => '2024-01-02 03:04:05',
    ]);

    try {
        runDomainProviderMigration();
        expect(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'account_id')->value('created_at'))
            ->toBe('2022-01-02 03:04:05');
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($dnsPath);
        File::deleteDirectory($registrarPath);
    }
});

it('accepts equivalent encrypted settings with different ciphertext', function (): void {
    insertLegacyDomainSetting('cloudflare-dns', 'api_token', Crypt::encryptString('[REDACTED]'), true);
    insertLegacyDomainSetting('cloudflare-registrar', 'api_token', Crypt::encryptString('[REDACTED]'), true);
    $preflight = require base_path('database/migrations/2026_08_28_115400_normalize_legacy_domain_secret_settings.php');
    $preflight->up();
    $migration = require base_path('database/migrations/2026_08_28_115500_split_domain_provider_extensions.php');
    $method = new ReflectionMethod($migration, 'collectMigratedSettings');
    $method->setAccessible(true);

    try {
        $settings = $method->invoke($migration, ['cloudflare-dns', 'cloudflare-registrar'], [
            'setting_map' => [
                'cloudflare-dns:api_token' => 'cloudflare_api_token',
                'cloudflare-registrar:api_token' => 'cloudflare_api_token',
            ],
            'setting_secrets' => ['cloudflare_api_token' => true],
            'ignored_setting_keys' => [],
        ]);

        expect($settings)->toHaveKey('cloudflare_api_token');
    } finally {
        DB::table('extension_settings')->whereIn('extension_id', ['cloudflare-dns', 'cloudflare-registrar'])->delete();
    }
});

it('removes a known orphan legacy package directory after migration', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $orphan = storage_path('app/packages/extensions/cloudflare-dns');
    File::ensureDirectoryExists($orphan);
    File::put($orphan.'/orphan.marker', 'legacy package without a database row');

    try {
        runDomainProviderMigration();

        expect(File::exists($orphan))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/cloudflare-domain')))->toBeTrue();
    } finally {
        File::deleteDirectory($orphan);
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
    }
});

it('retains a prior target package while legacy records still need migration', function (): void {
    $configuredRoot = config('agovena.packages.optional_packages_path');
    $destination = storage_path('app/packages/extensions/cloudflare-domain');
    $backup = storage_path('app/packages/extensions/.cloudflare-domain.backup');
    File::copyDirectory($configuredRoot.'/extensions/domains/cloudflare-domain', $destination);
    File::put($destination.'/prior.marker', 'current');
    File::ensureDirectoryExists($backup);
    File::put($backup.'/prior.marker', 'previous');
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainExtension('namecheap-registrar');
    $invalidNamecheapPath = insertLegacyDomainPackage('namecheap-registrar', 'unsupported', 'fixture-invalid-source');

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(File::get($destination.'/prior.marker'))->toBe('previous')
            ->and(File::exists($backup))->toBeFalse();
    } finally {
        File::deleteDirectory($destination);
        File::deleteDirectory($backup);
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory($invalidNamecheapPath);
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-dns'));
    }
});

it('removes a stale target backup only after canonical validation', function (): void {
    $configuredRoot = config('agovena.packages.optional_packages_path');
    $destination = storage_path('app/packages/extensions/cloudflare-domain');
    $backup = storage_path('app/packages/extensions/.cloudflare-domain.backup');
    File::copyDirectory($configuredRoot.'/extensions/domains/cloudflare-domain', $destination);
    File::ensureDirectoryExists($backup);
    File::put($backup.'/old-state.txt', 'stale backup');

    try {
        runDomainProviderMigration();

        expect(File::exists($destination))->toBeTrue()
            ->and(File::exists($backup))->toBeFalse();
    } finally {
        File::deleteDirectory($destination);
        File::deleteDirectory($backup);
    }
});

it('preserves package timestamps in the target and provenance metadata', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $legacyPath = insertLegacyDomainPackage('cloudflare-dns');
    $createdAt = '2024-01-02 03:04:05';
    $updatedAt = '2024-02-03 04:05:06';
    DB::table('agovena_packages')->where('agovena_id', 'cloudflare-dns')->update([
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);

    try {
        runDomainProviderMigration();
        $package = DB::table('agovena_packages')->where('agovena_id', 'cloudflare-domain')->first();
        $extension = DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->first();
        $meta = json_decode((string) $extension->meta, true, 512, JSON_THROW_ON_ERROR);

        expect($package->created_at)->toBe($createdAt)
            ->and($meta['_agovena_migration']['legacy_package_sources'][0]['created_at'])->toBe($createdAt)
            ->and($meta['_agovena_migration']['legacy_package_sources'][0]['updated_at'])->toBe($updatedAt);
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory($legacyPath);
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
            ->and(DB::table('agovena_packages')->where('agovena_id', 'namecheap-domain')->value('composer_name'))->toBeNull()
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'api_key')->value('value'))->toBe('[REDACTED]')
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'api_key')->value('is_secret'))->toBe(1)
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'sandbox')->value('value'))->toBe('1')
            ->and(File::exists($legacyPath))->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
});

it('splits an already unified Domain DNS installation into both provider extensions', function (): void {
    $legacyPath = insertLegacyDomainPackage('domain-dns');
    insertLegacyDomainExtension('domain-dns');
    insertLegacyDomainSetting('domain-dns', 'cloudflare_account_id', 'fixture-account');
    insertLegacyDomainSetting('domain-dns', 'cloudflare_api_token', '[REDACTED]', true);
    insertLegacyDomainSetting('domain-dns', 'namecheap_api_user', 'fixture-user');
    insertLegacyDomainSetting('domain-dns', 'namecheap_api_key', '[REDACTED]', true);
    insertLegacyDomainSetting('domain-dns', 'namecheap_username', 'fixture-user');
    insertLegacyDomainSetting('domain-dns', 'namecheap_client_ip', '198.51.100.10');
    insertLegacyDomainSetting('domain-dns', 'namecheap_sandbox', true);

    try {
        runDomainProviderMigration();
        runDomainProviderMigration();

        expect(DB::table('agovena_extensions')->whereIn('extension_id', ['cloudflare-domain', 'namecheap-domain'])->count())->toBe(2)
            ->and(DB::table('agovena_extensions')->where('extension_id', 'domain-dns')->exists())->toBeFalse()
            ->and(DB::table('agovena_packages')->whereIn('agovena_id', ['cloudflare-domain', 'namecheap-domain'])->count())->toBe(2)
            ->and(DB::table('extension_settings')->where('extension_id', 'cloudflare-domain')->where('key', 'api_token')->value('value'))->toBe('[REDACTED]')
            ->and(DB::table('extension_settings')->where('extension_id', 'namecheap-domain')->where('key', 'api_key')->value('value'))->toBe('[REDACTED]')
            ->and(File::exists($legacyPath))->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
});

it('cleans prepared targets when a later provider package cannot be prepared', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $cloudflareLegacyPath = insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainExtension('namecheap-registrar');
    $namecheapLegacyPath = insertLegacyDomainPackage('namecheap-registrar');

    $configuredRoot = config('agovena.packages.optional_packages_path');
    $root = storage_path('framework/testing/missing-namecheap-domain-package');
    File::copyDirectory($configuredRoot.'/extensions/domains/cloudflare-domain', $root.'/extensions/domains/cloudflare-domain');
    File::copyDirectory($configuredRoot.'/extensions/domains/namecheap-domain', $root.'/extensions/domains/namecheap-domain');
    File::delete($root.'/extensions/domains/namecheap-domain/lang/en/messages.php');
    config(['agovena.packages.optional_packages_path' => $root]);

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(File::exists(storage_path('app/packages/extensions/cloudflare-domain')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.staging')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.backup')))->toBeFalse();
    } finally {
        config(['agovena.packages.optional_packages_path' => $configuredRoot]);
        File::deleteDirectory($root);
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory($cloudflareLegacyPath);
        File::deleteDirectory($namecheapLegacyPath);
    }

    expect(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'namecheap-registrar')->exists())->toBeTrue();
});

it('restores an existing target package when the migration transaction fails', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $legacyPath = insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainSetting('cloudflare-dns', 'account_id', 'legacy-account');

    $destination = storage_path('app/packages/extensions/cloudflare-domain');
    File::ensureDirectoryExists($destination);
    File::put($destination.'/old-state.txt', 'old package state');
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
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(File::get($destination.'/old-state.txt'))->toBe('old package state')
            ->and(File::exists($destination.'/extension.json'))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.staging')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.backup')))->toBeFalse()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue();
    } finally {
        File::deleteDirectory($destination);
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
});

it('restores package state when the migration transaction fails after preparation', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $cloudflareLegacyPath = insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainExtension('namecheap-registrar');
    $namecheapLegacyPath = insertLegacyDomainPackage('namecheap-registrar');
    insertLegacyDomainSetting('namecheap-registrar', 'api_key', '[REDACTED]', true);

    DB::table('agovena_extensions')->insert([
        'extension_id' => 'namecheap-domain',
        'version' => '1.0.0',
        'enabled' => false,
        'installed_at' => now(),
        'enabled_at' => null,
        'disabled_at' => now(),
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    insertLegacyDomainSetting('namecheap-domain', 'api_key', '[REDACTED-CONFLICT]', true);

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(File::exists(storage_path('app/packages/extensions/cloudflare-domain')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/namecheap-domain')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.staging')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.cloudflare-domain.backup')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.namecheap-domain.staging')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/.namecheap-domain.backup')))->toBeFalse()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'namecheap-registrar')->exists())->toBeTrue();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/namecheap-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.namecheap-domain.backup'));
        File::deleteDirectory($cloudflareLegacyPath);
        File::deleteDirectory($namecheapLegacyPath);
    }
});

it('fails closed when any legacy package scheduled for removal has invalid source metadata', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $invalidPath = insertLegacyDomainPackage('cloudflare-dns', 'unsupported', 'fixture-invalid-source');
    insertLegacyDomainExtension('cloudflare-registrar');
    $validPath = insertLegacyDomainPackage('cloudflare-registrar');

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(DB::table('agovena_packages')->whereIn('agovena_id', ['cloudflare-dns', 'cloudflare-registrar'])->count())->toBe(2)
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-registrar')->exists())->toBeTrue()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($invalidPath);
        File::deleteDirectory($validPath);
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

it('fails closed for malformed source locators', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    insertLegacyDomainPackage('cloudflare-dns', 'monorepo', '');

    expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
        ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();

    File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
    File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-dns'));
});

it('fails closed for unsupported legacy settings after source validation', function (): void {
    insertLegacyDomainExtension('cloudflare-dns');
    $legacyPath = insertLegacyDomainPackage('cloudflare-dns');
    insertLegacyDomainSetting('cloudflare-dns', 'unknown', 'value');

    try {
        expect(fn (): mixed => runDomainProviderMigration())->toThrow(RuntimeException::class)
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-dns')->exists())->toBeTrue()
            ->and(DB::table('agovena_extensions')->where('extension_id', 'cloudflare-domain')->exists())->toBeFalse();
    } finally {
        File::deleteDirectory(storage_path('app/packages/extensions/cloudflare-domain'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.staging'));
        File::deleteDirectory(storage_path('app/packages/extensions/.cloudflare-domain.backup'));
        File::deleteDirectory($legacyPath);
    }
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

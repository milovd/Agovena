<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManifest;
use App\Enums\PackageSourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const TARGET = 'domain-dns';

    private const EXPECTED_PROVIDER = 'Agovena\\Extensions\\DomainDns\\DomainDnsServiceProvider';

    /** @var array<string, string> */
    private const EXPECTED_AUTOLOAD = [
        'Agovena\\Extensions\\DomainDns\\' => 'src/',
    ];

    /** @var list<string> */
    private const EXPECTED_MANIFEST_KEYS = [
        'id',
        'name',
        'version',
        'description',
        'author',
        'category',
        'production_ready',
        'agovena',
        'provider',
        'module_dependencies',
        'dependencies',
        'autoload',
        'settings',
    ];

    /** @var list<array{key: string, label: string, type: string, secret: bool, required: bool, default: mixed, help: string}> */
    private const EXPECTED_SETTINGS = [
        [
            'key' => 'cloudflare_account_id',
            'label' => 'domain-dns::messages.settings.cloudflare_account_id',
            'type' => 'string',
            'secret' => false,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.cloudflare_account_id_help',
        ],
        [
            'key' => 'cloudflare_api_token',
            'label' => 'domain-dns::messages.settings.cloudflare_api_token',
            'type' => 'string',
            'secret' => true,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.cloudflare_api_token_help',
        ],
        [
            'key' => 'namecheap_api_user',
            'label' => 'domain-dns::messages.settings.namecheap_api_user',
            'type' => 'string',
            'secret' => false,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.namecheap_api_user_help',
        ],
        [
            'key' => 'namecheap_api_key',
            'label' => 'domain-dns::messages.settings.namecheap_api_key',
            'type' => 'string',
            'secret' => true,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.namecheap_api_key_help',
        ],
        [
            'key' => 'namecheap_username',
            'label' => 'domain-dns::messages.settings.namecheap_username',
            'type' => 'string',
            'secret' => false,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.namecheap_username_help',
        ],
        [
            'key' => 'namecheap_client_ip',
            'label' => 'domain-dns::messages.settings.namecheap_client_ip',
            'type' => 'string',
            'secret' => false,
            'required' => false,
            'default' => '',
            'help' => 'domain-dns::messages.settings.namecheap_client_ip_help',
        ],
        [
            'key' => 'namecheap_sandbox',
            'label' => 'domain-dns::messages.settings.namecheap_sandbox',
            'type' => 'boolean',
            'secret' => false,
            'required' => false,
            'default' => true,
            'help' => 'domain-dns::messages.settings.namecheap_sandbox_help',
        ],
    ];

    /** @var list<string> */
    private const REQUIRED_FILES = [
        'src/CloudflareApi.php',
        'src/CloudflareDnsApi.php',
        'src/CloudflareDnsProvider.php',
        'src/CloudflareRegistrar.php',
        'src/CloudflareRegistrarOperationNotSupported.php',
        'src/DomainDnsExtension.php',
        'src/DomainDnsServiceProvider.php',
        'src/HttpCloudflareApi.php',
        'src/HttpCloudflareDnsApi.php',
        'src/HttpNamecheapApi.php',
        'src/NamecheapApi.php',
        'src/NamecheapRegistrar.php',
    ];

    /** @var array<string, string> */
    private const SETTING_MAP = [
        'cloudflare-registrar:account_id' => 'cloudflare_account_id',
        'cloudflare-dns:account_id' => 'cloudflare_account_id',
        'cloudflare-registrar:api_token' => 'cloudflare_api_token',
        'cloudflare-dns:api_token' => 'cloudflare_api_token',
        'namecheap-registrar:api_user' => 'namecheap_api_user',
        'namecheap-registrar:api_key' => 'namecheap_api_key',
        'namecheap-registrar:username' => 'namecheap_username',
        'namecheap-registrar:client_ip' => 'namecheap_client_ip',
        'namecheap-registrar:sandbox' => 'namecheap_sandbox',
    ];

    public function up(): void
    {
        $legacyIds = $this->legacyIds();
        $legacyExtensions = DB::table('agovena_extensions')
            ->whereIn('extension_id', $legacyIds)
            ->orderByDesc('enabled')
            ->orderByDesc('id')
            ->get();
        $legacyPackages = DB::table('agovena_packages')
            ->where('kind', 'extension')
            ->whereIn('agovena_id', $legacyIds)
            ->orderByDesc('id')
            ->get();

        if ($legacyExtensions->isEmpty() && $legacyPackages->isEmpty()) {
            return;
        }

        $packagePath = $this->materializeIntegratedPackage();
        if ($packagePath === null) {
            throw new RuntimeException('The unified Domain DNS package is not available. Install or materialize extensions/domains/domain-dns before upgrading. No legacy domain records were changed.');
        }

        $legacyExtension = $legacyExtensions->first();
        $legacyPackage = $legacyPackages->first();
        $now = now();
        $sourceType = $legacyPackage?->source_type ?? 'path';
        if (! in_array($sourceType, array_column(PackageSourceType::cases(), 'value'), true)) {
            throw new RuntimeException('The legacy Domain DNS package has an unsupported source type. No legacy domain records were changed.');
        }
        $sourceLocator = $legacyPackage?->source_locator;
        if ($sourceType === 'path' || ! is_string($sourceLocator) || trim($sourceLocator) === '') {
            $sourceLocator = $packagePath;
        }

        DB::transaction(function () use ($legacyIds, $legacyExtensions, $legacyExtension, $legacyPackage, $now, $sourceType, $sourceLocator, $packagePath): void {
            DB::table('agovena_extensions')->updateOrInsert(
                ['extension_id' => self::TARGET],
                [
                    'version' => '1.0.0',
                    'enabled' => (bool) $legacyExtensions->contains(fn (object $row): bool => (bool) $row->enabled),
                    'installed_at' => $legacyExtension?->installed_at ?? $now,
                    'enabled_at' => $legacyExtensions->firstWhere('enabled', true)?->enabled_at,
                    'disabled_at' => null,
                    'meta' => $legacyExtension?->meta ?? json_encode([]),
                    'created_at' => $legacyExtension?->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('agovena_packages')->updateOrInsert(
                ['kind' => 'extension', 'agovena_id' => self::TARGET],
                [
                    'composer_name' => self::TARGET,
                    'source_type' => $sourceType,
                    'source_locator' => $sourceLocator,
                    'version_constraint' => $legacyPackage?->version_constraint ?: '*',
                    'installed_version' => '1.0.0',
                    'available_version' => '1.0.0',
                    'install_path' => $packagePath,
                    'is_bundled' => false,
                    'created_at' => $legacyPackage?->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );

            foreach (self::SETTING_MAP as $legacy => $target) {
                [$legacyId, $legacyKey] = explode(':', $legacy, 2);
                $setting = DB::table('extension_settings')
                    ->where('extension_id', $legacyId)
                    ->where('key', $legacyKey)
                    ->first();
                if ($setting === null) {
                    continue;
                }

                $exists = DB::table('extension_settings')
                    ->where('extension_id', self::TARGET)
                    ->where('key', $target)
                    ->exists();
                if (! $exists) {
                    DB::table('extension_settings')->insert([
                        'extension_id' => self::TARGET,
                        'key' => $target,
                        'value' => $setting->value,
                        'is_secret' => $setting->is_secret,
                        'created_at' => $setting->created_at,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('extension_settings')->whereIn('extension_id', $legacyIds)->delete();
            DB::table('agovena_extensions')->whereIn('extension_id', $legacyIds)->delete();
            DB::table('agovena_packages')
                ->where('kind', 'extension')
                ->whereIn('agovena_id', $legacyIds)
                ->delete();
        });

        $this->cleanupLegacyPackageDirectories($legacyPackages);
    }

    public function down(): void
    {
        throw new RuntimeException('The unified Domain DNS migration is intentionally irreversible. Restore the database backup to roll it back.');
    }

    /** @return list<string> */
    private function legacyIds(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $key): string => explode(':', $key, 2)[0],
            array_keys(self::SETTING_MAP),
        )));
    }

    private function materializeIntegratedPackage(): ?string
    {
        $destination = storage_path('app/packages/extensions/'.self::TARGET);
        if ($this->hasIntegratedManifest($destination)) {
            return realpath($destination) ?: $destination;
        }

        $optionalRoot = config('agovena.packages.optional_packages_path');
        if (! is_string($optionalRoot) || trim($optionalRoot) === '') {
            return null;
        }

        $source = rtrim(trim($optionalRoot), '/\\').DIRECTORY_SEPARATOR.'extensions'.DIRECTORY_SEPARATOR.'domains'.DIRECTORY_SEPARATOR.self::TARGET;
        if (! $this->hasIntegratedManifest($source)) {
            return null;
        }

        File::ensureDirectoryExists(dirname($destination));
        if (is_dir($destination)) {
            File::deleteDirectory($destination);
        }
        File::copyDirectory($source, $destination);

        if (! $this->hasIntegratedManifest($destination)) {
            File::deleteDirectory($destination);

            return null;
        }

        return realpath($destination) ?: $destination;
    }

    private function hasIntegratedManifest(string $directory): bool
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'extension.json';
        if (! is_file($manifestPath)) {
            return false;
        }

        $manifestData = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifestData)) {
            return false;
        }

        try {
            $manifest = ExtensionManifest::fromArray($manifestData, $directory);
        } catch (Throwable) {
            return false;
        }

        $manifestKeys = array_keys($manifestData);
        $expectedManifestKeys = self::EXPECTED_MANIFEST_KEYS;
        sort($manifestKeys);
        sort($expectedManifestKeys);

        if ($manifestKeys !== $expectedManifestKeys
            || ($manifestData['description'] ?? null) !== 'Domain availability, registration, renewals and Cloudflare DNS management in one domain integration.'
            || ($manifestData['author'] ?? null) !== 'Agovena'
            || ($manifestData['production_ready'] ?? null) !== false
            || ($manifestData['agovena'] ?? null) !== '^0.0.1'
            || ($manifestData['module_dependencies'] ?? null) !== ['domains']
            || ($manifestData['dependencies'] ?? null) !== []
            || ($manifestData['autoload'] ?? null) !== ['psr-4' => self::EXPECTED_AUTOLOAD]
            || ($manifestData['settings'] ?? null) !== self::EXPECTED_SETTINGS
            || $manifest->id !== self::TARGET
            || $manifest->name !== 'Domain DNS'
            || $manifest->version !== '1.0.0'
            || $manifest->provider !== self::EXPECTED_PROVIDER
            || $manifest->category !== ExtensionCategory::Domain
            || $manifest->agovena !== '^0.0.1'
            || $manifest->dependencies !== []
            || $manifest->moduleDependencies !== ['domains']
            || $manifest->author !== 'Agovena'
            || $manifest->settings !== self::EXPECTED_SETTINGS
            || $manifest->autoloadPsr4 !== self::EXPECTED_AUTOLOAD
            || $manifest->productionReady
        ) {
            return false;
        }

        foreach (self::REQUIRED_FILES as $relativePath) {
            if (! is_file($directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
                return false;
            }
        }

        return true;
    }

    /** @param  iterable<object>  $legacyPackages */
    private function cleanupLegacyPackageDirectories(iterable $legacyPackages): void
    {
        $allowedRoot = realpath(storage_path('app/packages/extensions'));
        if ($allowedRoot === false) {
            return;
        }

        foreach ($legacyPackages as $package) {
            $legacyId = (string) ($package->agovena_id ?? '');
            if (! in_array($legacyId, $this->legacyIds(), true)) {
                continue;
            }

            $path = realpath((string) ($package->install_path ?? ''));
            if ($path === false || ! str_starts_with($path, $allowedRoot.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if ($path === realpath(storage_path('app/packages/extensions/'.self::TARGET))
                || basename($path) !== $legacyId) {
                continue;
            }

            File::deleteDirectory($path);
        }
    }
};

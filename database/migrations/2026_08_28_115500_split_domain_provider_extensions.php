<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManifest;
use App\Enums\PackageSourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const VERSION = '1.0.0';

    private const AGOVENA_CONSTRAINT = '^0.0.1';

    private const FILESYSTEM_RETRY_ATTEMPTS = 50;

    private const FILESYSTEM_RETRY_DELAY_MICROSECONDS = 100_000;

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        return [
            'cloudflare-domain' => [
                'legacy_ids' => ['cloudflare-dns', 'cloudflare-registrar', 'domain-dns'],
                'manifest' => [
                    'id' => 'cloudflare-domain',
                    'name' => 'Cloudflare Domains',
                    'version' => self::VERSION,
                    'description' => 'Cloudflare domain availability, registration and DNS management in one provider integration.',
                    'author' => 'Agovena',
                    'category' => 'domain',
                    'production_ready' => false,
                    'agovena' => self::AGOVENA_CONSTRAINT,
                    'provider' => 'Agovena\\Extensions\\CloudflareDomain\\CloudflareDomainServiceProvider',
                    'module_dependencies' => ['domains'],
                    'dependencies' => [],
                    'autoload' => [
                        'psr-4' => [
                            'Agovena\\Extensions\\CloudflareDomain\\' => 'src/',
                        ],
                    ],
                    'settings' => [
                        [
                            'key' => 'account_id',
                            'label' => 'cloudflare-domain::messages.settings.account_id',
                            'type' => 'string',
                            'secret' => false,
                            'required' => false,
                            'default' => '',
                            'help' => 'cloudflare-domain::messages.settings.account_id_help',
                        ],
                        [
                            'key' => 'api_token',
                            'label' => 'cloudflare-domain::messages.settings.api_token',
                            'type' => 'string',
                            'secret' => true,
                            'required' => false,
                            'default' => '',
                            'help' => 'cloudflare-domain::messages.settings.api_token_help',
                        ],
                    ],
                ],
                'required_files' => [
                    'src/CloudflareApi.php',
                    'src/CloudflareDnsApi.php',
                    'src/CloudflareDnsProvider.php',
                    'src/CloudflareRegistrar.php',
                    'src/CloudflareRegistrarOperationNotSupported.php',
                    'src/CloudflareDomainExtension.php',
                    'src/CloudflareDomainServiceProvider.php',
                    'src/HttpCloudflareApi.php',
                    'src/HttpCloudflareDnsApi.php',
                    'lang/en/messages.php',
                    'lang/nl/messages.php',
                ],
                'setting_map' => [
                    'cloudflare-dns:account_id' => 'account_id',
                    'cloudflare-dns:api_token' => 'api_token',
                    'cloudflare-registrar:account_id' => 'account_id',
                    'cloudflare-registrar:api_token' => 'api_token',
                    'domain-dns:cloudflare_account_id' => 'account_id',
                    'domain-dns:cloudflare_api_token' => 'api_token',
                ],
                'setting_secrets' => [
                    'account_id' => false,
                    'api_token' => true,
                ],
                'ignored_setting_keys' => [
                    'domain-dns:namecheap_api_user',
                    'domain-dns:namecheap_api_key',
                    'domain-dns:namecheap_username',
                    'domain-dns:namecheap_client_ip',
                    'domain-dns:namecheap_sandbox',
                ],
            ],
            'namecheap-domain' => [
                'legacy_ids' => ['namecheap-registrar', 'domain-dns'],
                'manifest' => [
                    'id' => 'namecheap-domain',
                    'name' => 'Namecheap Domains',
                    'version' => self::VERSION,
                    'description' => 'Namecheap domain availability, registration and renewal management in one provider integration.',
                    'author' => 'Agovena',
                    'category' => 'domain',
                    'production_ready' => false,
                    'agovena' => self::AGOVENA_CONSTRAINT,
                    'provider' => 'Agovena\\Extensions\\NamecheapDomain\\NamecheapDomainServiceProvider',
                    'module_dependencies' => ['domains'],
                    'dependencies' => [],
                    'autoload' => [
                        'psr-4' => [
                            'Agovena\\Extensions\\NamecheapDomain\\' => 'src/',
                        ],
                    ],
                    'settings' => [
                        [
                            'key' => 'api_user',
                            'label' => 'namecheap-domain::messages.settings.api_user',
                            'type' => 'string',
                            'secret' => false,
                            'required' => false,
                            'default' => '',
                            'help' => 'namecheap-domain::messages.settings.api_user_help',
                        ],
                        [
                            'key' => 'api_key',
                            'label' => 'namecheap-domain::messages.settings.api_key',
                            'type' => 'string',
                            'secret' => true,
                            'required' => false,
                            'default' => '',
                            'help' => 'namecheap-domain::messages.settings.api_key_help',
                        ],
                        [
                            'key' => 'username',
                            'label' => 'namecheap-domain::messages.settings.username',
                            'type' => 'string',
                            'secret' => false,
                            'required' => false,
                            'default' => '',
                            'help' => 'namecheap-domain::messages.settings.username_help',
                        ],
                        [
                            'key' => 'client_ip',
                            'label' => 'namecheap-domain::messages.settings.client_ip',
                            'type' => 'string',
                            'secret' => false,
                            'required' => false,
                            'default' => '',
                            'help' => 'namecheap-domain::messages.settings.client_ip_help',
                        ],
                        [
                            'key' => 'sandbox',
                            'label' => 'namecheap-domain::messages.settings.sandbox',
                            'type' => 'boolean',
                            'secret' => false,
                            'required' => false,
                            'default' => true,
                            'help' => 'namecheap-domain::messages.settings.sandbox_help',
                        ],
                    ],
                ],
                'required_files' => [
                    'src/NamecheapApi.php',
                    'src/HttpNamecheapApi.php',
                    'src/NamecheapRegistrar.php',
                    'src/NamecheapDomainExtension.php',
                    'src/NamecheapDomainServiceProvider.php',
                    'lang/en/messages.php',
                    'lang/nl/messages.php',
                ],
                'setting_map' => [
                    'namecheap-registrar:api_user' => 'api_user',
                    'namecheap-registrar:api_key' => 'api_key',
                    'namecheap-registrar:username' => 'username',
                    'namecheap-registrar:client_ip' => 'client_ip',
                    'namecheap-registrar:sandbox' => 'sandbox',
                    'domain-dns:namecheap_api_user' => 'api_user',
                    'domain-dns:namecheap_api_key' => 'api_key',
                    'domain-dns:namecheap_username' => 'username',
                    'domain-dns:namecheap_client_ip' => 'client_ip',
                    'domain-dns:namecheap_sandbox' => 'sandbox',
                ],
                'setting_secrets' => [
                    'api_user' => false,
                    'api_key' => true,
                    'username' => false,
                    'client_ip' => false,
                    'sandbox' => false,
                ],
                'ignored_setting_keys' => [
                    'domain-dns:cloudflare_account_id',
                    'domain-dns:cloudflare_api_token',
                ],
            ],
        ];
    }

    public function up(): void
    {
        $plans = [];

        foreach ($this->definitions() as $targetId => $definition) {
            $plan = $this->prepareTargetMigration($targetId, $definition);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if ($plans === []) {
            return;
        }

        DB::transaction(function () use ($plans): void {
            foreach ($plans as $plan) {
                $this->applyTargetMigration($plan);
            }
        });

        foreach ($plans as $plan) {
            $this->cleanupLegacyPackageDirectories($plan['legacy_packages']);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The unified Domain provider migration is intentionally irreversible. Restore the database backup to roll it back.');
    }

    /** @param array<string, mixed> $definition @return array<string, mixed>|null */
    private function prepareTargetMigration(string $targetId, array $definition): ?array
    {
        $legacyIds = $definition['legacy_ids'];
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
        $legacySettingsExist = DB::table('extension_settings')
            ->whereIn('extension_id', $legacyIds)
            ->exists();

        if ($legacyExtensions->isEmpty() && $legacyPackages->isEmpty() && ! $legacySettingsExist) {
            return null;
        }

        $legacyPackage = $legacyPackages->first();
        $sourceType = $legacyPackage?->source_type ?? PackageSourceType::Path->value;
        if (PackageSourceType::tryFrom((string) $sourceType) === null) {
            throw new RuntimeException("The {$targetId} package has an unsupported source type. No legacy domain records were changed.");
        }

        $sourceLocator = $legacyPackage?->source_locator;
        if ($sourceType !== PackageSourceType::Path->value
            && (! is_string($sourceLocator) || trim($sourceLocator) === '')
        ) {
            throw new RuntimeException("The {$targetId} package has an invalid source locator. No legacy domain records were changed.");
        }

        $migratedSettings = $this->collectMigratedSettings($legacyIds, $definition);
        $packagePath = $this->materializeIntegratedPackage($targetId, $definition);
        if ($packagePath === null) {
            throw new RuntimeException("The {$targetId} package is unavailable or invalid. No legacy domain records were changed.");
        }
        if ($sourceType === PackageSourceType::Path->value) {
            $sourceLocator = $packagePath;
        }
        $targetExtension = DB::table('agovena_extensions')->where('extension_id', $targetId)->first();
        $targetPackage = DB::table('agovena_packages')
            ->where('kind', 'extension')
            ->where('agovena_id', $targetId)
            ->first();

        return [
            'target_id' => $targetId,
            'definition' => $definition,
            'legacy_ids' => $legacyIds,
            'legacy_extensions' => $legacyExtensions,
            'legacy_extension' => $legacyExtensions->first(),
            'legacy_packages' => $legacyPackages,
            'legacy_package' => $legacyPackage,
            'migrated_settings' => $migratedSettings,
            'target_extension' => $targetExtension,
            'target_package' => $targetPackage,
            'source_type' => (string) $sourceType,
            'source_locator' => (string) $sourceLocator,
            'package_path' => $packagePath,
            'now' => now(),
        ];
    }

    /** @param array<string, mixed> $plan */
    private function applyTargetMigration(array $plan): void
    {
        $targetId = $plan['target_id'];
        $definition = $plan['definition'];
        $legacyExtensions = $plan['legacy_extensions'];
        $legacyExtension = $plan['legacy_extension'];
        $legacyPackage = $plan['legacy_package'];
        $targetExtension = $plan['target_extension'];
        $targetPackage = $plan['target_package'];
        $now = $plan['now'];

        $enabled = (bool) ($targetExtension?->enabled ?? false)
            || $legacyExtensions->contains(fn (object $row): bool => (bool) $row->enabled);
        $installedAt = $targetExtension?->installed_at
            ?? $legacyExtension?->installed_at
            ?? $now;
        $meta = $targetExtension?->meta
            ?? $legacyExtension?->meta
            ?? json_encode([]);

        DB::table('agovena_extensions')->updateOrInsert(
            ['extension_id' => $targetId],
            [
                'version' => self::VERSION,
                'enabled' => $enabled,
                'installed_at' => $installedAt,
                'enabled_at' => $enabled
                    ? ($targetExtension?->enabled_at ?? $legacyExtensions->firstWhere('enabled', true)?->enabled_at ?? $now)
                    : null,
                'disabled_at' => $enabled ? null : ($targetExtension?->disabled_at ?? $now),
                'meta' => $meta,
                'created_at' => $targetExtension?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        DB::table('agovena_packages')->updateOrInsert(
            [
                'kind' => 'extension',
                'agovena_id' => $targetId,
            ],
            [
                'composer_name' => $targetId,
                'source_type' => $plan['source_type'],
                'source_locator' => $plan['source_locator'],
                'version_constraint' => $legacyPackage?->version_constraint
                    ?? $targetPackage?->version_constraint
                    ?? '*',
                'installed_version' => self::VERSION,
                'available_version' => self::VERSION,
                'install_path' => $plan['package_path'],
                'is_bundled' => false,
                'created_at' => $targetPackage?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        foreach ($plan['migrated_settings'] as $targetKey => $setting) {
            $existing = DB::table('extension_settings')
                ->where('extension_id', $targetId)
                ->where('key', $targetKey)
                ->first();
            $expectedSecret = $definition['setting_secrets'][$targetKey];

            if ($existing !== null) {
                if ($existing->value !== $setting->value || (bool) $existing->is_secret !== $expectedSecret) {
                    throw new RuntimeException("The {$targetId} setting conflicts with an existing unified setting. No legacy domain records were changed.");
                }

                continue;
            }

            DB::table('extension_settings')->insert([
                'extension_id' => $targetId,
                'key' => $targetKey,
                'value' => $setting->value,
                'is_secret' => $expectedSecret,
                'created_at' => $setting->created_at,
                'updated_at' => $now,
            ]);
        }

        DB::table('extension_settings')->whereIn('extension_id', $plan['legacy_ids'])->delete();
        DB::table('agovena_extensions')->whereIn('extension_id', $plan['legacy_ids'])->delete();
        DB::table('agovena_packages')
            ->where('kind', 'extension')
            ->whereIn('agovena_id', $plan['legacy_ids'])
            ->delete();
    }

    /** @param list<string> $legacyIds @param array<string, mixed> $definition @return array<string, object> */
    private function collectMigratedSettings(array $legacyIds, array $definition): array
    {
        $settings = [];

        foreach (DB::table('extension_settings')->whereIn('extension_id', $legacyIds)->get() as $setting) {
            $legacyKey = $setting->extension_id.':'.$setting->key;
            if (! array_key_exists($legacyKey, $definition['setting_map'])) {
                if (in_array($legacyKey, $definition['ignored_setting_keys'] ?? [], true)) {
                    continue;
                }

                throw new RuntimeException('The legacy Domain provider contains an unmapped setting. No legacy domain records were changed.');
            }

            $targetKey = $definition['setting_map'][$legacyKey];
            if ((bool) $setting->is_secret !== $definition['setting_secrets'][$targetKey]) {
                throw new RuntimeException('The legacy Domain provider contains invalid setting secret metadata. No legacy domain records were changed.');
            }

            if (isset($settings[$targetKey]) && $settings[$targetKey]->value !== $setting->value) {
                throw new RuntimeException('The legacy Domain provider contains conflicting setting values. No legacy domain records were changed.');
            }

            $settings[$targetKey] = $setting;
        }

        return $settings;
    }

    /** @param array<string, mixed> $definition */
    private function materializeIntegratedPackage(string $targetId, array $definition): ?string
    {
        $destination = storage_path('app/packages/extensions/'.$targetId);
        if ($this->hasCanonicalManifest($destination, $definition)) {
            return realpath($destination) ?: $destination;
        }

        $optionalRoot = config('agovena.packages.optional_packages_path');
        if (! is_string($optionalRoot) || trim($optionalRoot) === '') {
            return null;
        }

        $optionalRoot = realpath($optionalRoot) ?: realpath(base_path(trim($optionalRoot)));
        if ($optionalRoot === false) {
            return null;
        }

        $source = $optionalRoot.DIRECTORY_SEPARATOR.'extensions'.DIRECTORY_SEPARATOR.'domains'.DIRECTORY_SEPARATOR.$targetId;
        $sourceIsValid = false;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($this->hasCanonicalManifest($source, $definition)) {
                $sourceIsValid = true;

                break;
            }

            usleep(100_000);
        }
        if (! $sourceIsValid) {
            return null;
        }

        $staging = storage_path('app/packages/extensions/.'.$targetId.'.staging');
        $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
        File::ensureDirectoryExists(dirname($destination));

        try {
            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                throw new RuntimeException("Unable to clear the staged {$targetId} package.");
            }

            if (File::exists($backup)) {
                if ($this->hasCanonicalManifest($destination, $definition)) {
                    if (! $this->deleteFilesystemPath($backup)) {
                        throw new RuntimeException("Unable to remove the stale {$targetId} package backup.");
                    }
                } else {
                    if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                        throw new RuntimeException("Unable to clear the invalid {$targetId} package destination.");
                    }
                    if (! $this->renameFilesystemPath($backup, $destination)) {
                        throw new RuntimeException("Unable to restore the {$targetId} package backup.");
                    }
                }
            }

            $stagingIsValid = false;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                    usleep(100_000);

                    continue;
                }

                $copied = File::copyDirectory($source, $staging);
                $stagingCanonical = $this->hasCanonicalManifest($staging, $definition);
                if ($copied && $stagingCanonical) {
                    $stagingIsValid = true;

                    break;
                }

                if (File::exists($staging)) {
                    $this->deleteFilesystemPath($staging);
                }

                usleep(100_000);
            }
            if (! $stagingIsValid) {
                if (File::exists($staging)) {
                    throw new RuntimeException("Unable to clean up the invalid staged {$targetId} package.");
                }

                return null;
            }

            $hadDestination = File::exists($destination);
            if ($hadDestination && ! $this->renameFilesystemPath($destination, $backup)) {
                throw new RuntimeException("Unable to stage the existing {$targetId} package.");
            }

            $activated = $this->renameFilesystemPath($staging, $destination);
            if (! $activated) {
                $activated = $this->copyFilesystemPath($staging, $destination, $definition);
                if ($activated) {
                    if (! $this->deleteFilesystemPath($staging)) {
                        throw new RuntimeException("Unable to clean up the staged {$targetId} package after activation.");
                    }
                }
            }

            if (! $activated) {
                if ($hadDestination && File::exists($backup)
                    && ! $this->renameFilesystemPath($backup, $destination)) {
                    throw new RuntimeException("Unable to restore the {$targetId} package after activation failed.");
                }

                throw new RuntimeException("Unable to activate the {$targetId} package.");
            }

            if (! $this->hasCanonicalManifest($destination, $definition)) {
                if (! $this->deleteFilesystemPath($destination)) {
                    throw new RuntimeException("Unable to remove the invalid {$targetId} package destination.");
                }
                if ($hadDestination && File::exists($backup)
                    && ! $this->renameFilesystemPath($backup, $destination)) {
                    throw new RuntimeException("Unable to restore the {$targetId} package after validation failed.");
                }

                return null;
            }

            if (File::exists($backup) && ! $this->deleteFilesystemPath($backup)) {
                throw new RuntimeException("Unable to remove the {$targetId} package backup after activation.");
            }
        } catch (Throwable) {
            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                throw new RuntimeException("Unable to clean up the staged {$targetId} package.");
            }

            if (File::exists($backup) && ! $this->hasCanonicalManifest($destination, $definition)) {
                if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                    throw new RuntimeException("Unable to clear the failed {$targetId} package destination.");
                }
                if (! $this->renameFilesystemPath($backup, $destination)) {
                    throw new RuntimeException("Unable to restore the {$targetId} package after migration failed.");
                }
            }

            return null;
        }

        return realpath($destination) ?: $destination;
    }

    /** @param array<string, mixed> $definition */
    private function copyFilesystemPath(string $source, string $destination, array $definition): bool
    {
        for ($attempt = 0; $attempt < self::FILESYSTEM_RETRY_ATTEMPTS; $attempt++) {
            if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                usleep(self::FILESYSTEM_RETRY_DELAY_MICROSECONDS);

                continue;
            }

            if (File::copyDirectory($source, $destination)
                && $this->hasCanonicalManifest($destination, $definition)) {
                return true;
            }

            if (File::exists($destination)) {
                $this->deleteFilesystemPath($destination);
            }

            usleep(self::FILESYSTEM_RETRY_DELAY_MICROSECONDS);
        }

        return false;
    }

    private function renameFilesystemPath(string $source, string $destination): bool
    {
        for ($attempt = 0; $attempt < self::FILESYSTEM_RETRY_ATTEMPTS; $attempt++) {
            clearstatcache(true, $source);
            clearstatcache(true, $destination);

            if (@rename($source, $destination)) {
                return true;
            }

            usleep(self::FILESYSTEM_RETRY_DELAY_MICROSECONDS);
        }

        return false;
    }

    private function deleteFilesystemPath(string $path): bool
    {
        for ($attempt = 0; $attempt < self::FILESYSTEM_RETRY_ATTEMPTS; $attempt++) {
            clearstatcache(true, $path);

            if (! is_dir($path) && ! File::exists($path)) {
                return true;
            }

            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (File::exists($path)) {
                File::delete($path);
            }

            clearstatcache(true, $path);
            if (! is_dir($path) && ! File::exists($path)) {
                return true;
            }

            usleep(self::FILESYSTEM_RETRY_DELAY_MICROSECONDS);
        }

        return false;
    }

    /** @param array<string, mixed> $definition */
    private function hasCanonicalManifest(string $directory, array $definition): bool
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'extension.json';
        if (! is_file($manifestPath)) {
            return false;
        }

        $manifestData = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifestData)
            || $this->canonicalize($manifestData) !== $this->canonicalize($definition['manifest'])) {
            return false;
        }

        try {
            ExtensionManifest::fromArray($manifestData, $directory);
        } catch (Throwable) {
            return false;
        }

        foreach ($definition['required_files'] as $relativePath) {
            $path = $directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (! is_file($path)) {
                return false;
            }
        }

        return true;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalize($item);
        }
        ksort($canonical);

        return $canonical;
    }

    /** @param iterable<object> $legacyPackages */
    private function cleanupLegacyPackageDirectories(iterable $legacyPackages): void
    {
        $allowedRoot = realpath(storage_path('app/packages/extensions'));
        if ($allowedRoot === false) {
            return;
        }
        $allowedLegacyIds = array_keys($this->legacyToTarget());
        $allowedRoot = $this->normalize($allowedRoot);

        foreach ($legacyPackages as $package) {
            $legacyId = (string) ($package->agovena_id ?? '');
            if (! in_array($legacyId, $allowedLegacyIds, true)) {
                continue;
            }

            $path = realpath((string) ($package->install_path ?? ''));
            if ($path === false) {
                continue;
            }
            $path = $this->normalize($path);
            if ($this->normalize(dirname($path)) !== $allowedRoot || basename($path) !== $legacyId) {
                continue;
            }

            if (! $this->deleteFilesystemPath($path)) {
                throw new RuntimeException("Unable to remove the legacy {$legacyId} package directory.");
            }
        }
    }

    /** @return array<string, string> */
    private function legacyToTarget(): array
    {
        $map = [];
        foreach ($this->definitions() as $targetId => $definition) {
            foreach ($definition['legacy_ids'] as $legacyId) {
                $map[$legacyId] = $targetId;
            }
        }

        return $map;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
};

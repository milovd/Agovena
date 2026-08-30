<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManifest;
use App\Enums\PackageSourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const VERSION = '1.0.0';

    private const AGOVENA_CONSTRAINT = '^0.0.1';

    private const FILESYSTEM_RETRY_ATTEMPTS = 150;

    private const FILESYSTEM_RETRY_DELAY_MICROSECONDS = 100_000;

    /** @var array<string, array{destination_existed: bool}> */
    private array $newlyMaterializedTargets = [];

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
        $lockPath = storage_path('app/packages/.domain-provider-migration.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false || ! @flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Unable to acquire the domain provider migration lock.');
        }

        try {
            $this->upLocked();
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function upLocked(): void
    {
        $this->recoverMaterializationJournals();
        $plans = [];
        $migrationNow = now()->toImmutable();

        try {
            foreach ($this->definitions() as $targetId => $definition) {
                $plan = $this->prepareTargetMigration($targetId, $definition, $migrationNow);
                if ($plan !== null) {
                    $plans[] = $plan;
                }
            }

            if ($plans === []) {
                $this->cleanupOrphanTargetBackups();
                $this->cleanupOrphanLegacyPackageDirectories();

                return;
            }

            DB::transaction(function () use ($plans): void {
                foreach ($plans as $plan) {
                    $this->applyTargetMigration($plan);
                }
            });
        } catch (Throwable $exception) {
            try {
                $this->cleanupNewlyMaterializedTargets();
            } catch (Throwable $cleanupException) {
                throw new RuntimeException(
                    'Domain migration failed and package cleanup also failed: '.$cleanupException->getMessage(),
                    0,
                    $exception,
                );
            }

            throw $exception;
        }

        $this->cleanupCommittedMaterializationBackups();
        $this->cleanupOrphanTargetBackups();
        foreach ($plans as $plan) {
            $this->cleanupLegacyPackageDirectories($plan['legacy_packages']);
        }
        $this->cleanupOrphanLegacyPackageDirectories();
        $this->newlyMaterializedTargets = [];
    }

    private function recoverMaterializationJournals(): void
    {
        foreach ($this->definitions() as $targetId => $definition) {
            $this->recoverMaterializationJournal($targetId, $definition);
        }
    }

    /** @param array<string, mixed> $definition */
    private function recoverMaterializationJournal(string $targetId, array $definition): void
    {
        $extensionRoot = storage_path('app/packages/extensions');
        $destination = $extensionRoot.DIRECTORY_SEPARATOR.$targetId;
        $staging = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.staging';
        $backup = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.backup';
        $journal = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.materialization.json';
        if (! File::exists($journal)) {
            return;
        }

        try {
            $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException("The {$targetId} materialization journal is malformed.", 0, $exception);
        }
        if (! is_array($payload)
            || ($payload['target_id'] ?? null) !== $targetId
            || ($payload['destination'] ?? null) !== $destination
            || ($payload['staging'] ?? null) !== $staging
            || ($payload['backup'] ?? null) !== $backup
            || ! in_array($payload['phase'] ?? null, ['prepared', 'staged', 'backed_up', 'activated'], true)
        ) {
            throw new RuntimeException("The {$targetId} materialization journal failed validation.");
        }

        $hasLegacyRecords = $this->hasLegacyRecords($definition['legacy_ids']);
        if (! $hasLegacyRecords && $this->hasCanonicalManifest($destination, $definition)) {
            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                throw new RuntimeException("Unable to remove the stale {$targetId} staged package.");
            }
            if (File::exists($backup) && ! $this->deleteFilesystemPath($backup)) {
                throw new RuntimeException("Unable to remove the committed {$targetId} package backup.");
            }
            if (! File::delete($journal)) {
                throw new RuntimeException("Unable to remove the committed {$targetId} materialization journal.");
            }

            return;
        }

        if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
            throw new RuntimeException("Unable to remove the incomplete {$targetId} staged package.");
        }
        if (File::exists($backup)) {
            if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                throw new RuntimeException("Unable to clear the incomplete {$targetId} package destination.");
            }
            if (! $this->renameFilesystemPath($backup, $destination)) {
                throw new RuntimeException("Unable to restore the {$targetId} package backup.");
            }
        } elseif (($payload['destination_existed'] ?? false) !== true
            && File::exists($destination)
            && ! $this->deleteFilesystemPath($destination)
        ) {
            throw new RuntimeException("Unable to remove the incomplete {$targetId} package destination.");
        }

        if (! File::delete($journal)) {
            throw new RuntimeException("Unable to remove the recovered {$targetId} materialization journal.");
        }
    }

    private function cleanupNewlyMaterializedTargets(): void
    {
        $targets = $this->newlyMaterializedTargets;
        $this->newlyMaterializedTargets = [];
        $failures = [];

        foreach (array_reverse(array_keys($targets)) as $targetId) {
            $target = $targets[$targetId];
            $destination = storage_path('app/packages/extensions/'.$targetId);
            $staging = storage_path('app/packages/extensions/.'.$targetId.'.staging');
            $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');

            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                $failures[] = $staging;
            }

            if (File::exists($backup)) {
                if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                    $failures[] = $destination;
                }
                if (! File::exists($destination) && ! $this->renameFilesystemPath($backup, $destination)) {
                    $failures[] = $backup;
                }
            } elseif (! $target['destination_existed']
                && File::exists($destination)
                && ! $this->deleteFilesystemPath($destination)
            ) {
                $failures[] = $destination;
            }

            $journal = storage_path('app/packages/extensions/.'.$targetId.'.materialization.json');
            if (File::exists($journal) && ! File::delete($journal)) {
                $failures[] = $journal;
            }
        }

        if ($failures !== []) {
            throw new RuntimeException('Unable to clean up newly materialized Domain packages: '.implode(', ', $failures));
        }
    }

    private function cleanupCommittedMaterializationBackups(): void
    {
        $failures = [];

        foreach (array_keys($this->newlyMaterializedTargets) as $targetId) {
            $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
            if (File::exists($backup) && ! $this->deleteFilesystemPath($backup)) {
                $failures[] = $backup;
            }
            $journal = storage_path('app/packages/extensions/.'.$targetId.'.materialization.json');
            if (File::exists($journal) && ! File::delete($journal)) {
                $failures[] = $journal;
            }
        }

        if ($failures !== []) {
            throw new RuntimeException('Unable to remove committed Domain package backups: '.implode(', ', $failures));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The unified Domain provider migration is intentionally irreversible. Restore the database backup to roll it back.');
    }

    /** @param array<string, mixed> $definition @return array<string, mixed>|null */
    private function prepareTargetMigration(string $targetId, array $definition, CarbonImmutable $migrationNow): ?array
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
            ->orderBy('agovena_id')
            ->orderBy('id')
            ->get();
        $legacySettingsExist = DB::table('extension_settings')
            ->whereIn('extension_id', $legacyIds)
            ->exists();

        if ($legacyExtensions->isEmpty() && $legacyPackages->isEmpty() && ! $legacySettingsExist) {
            return null;
        }

        $legacyPackage = $legacyPackages->first();
        foreach ($legacyPackages as $package) {
            $packageSourceType = (string) $package->source_type;
            if (PackageSourceType::tryFrom($packageSourceType) === null) {
                throw new RuntimeException("The {$targetId} package has an unsupported source type. No legacy domain records were changed.");
            }

            if ($packageSourceType !== PackageSourceType::Path->value
                && (! is_string($package->source_locator) || trim($package->source_locator) === '')
            ) {
                throw new RuntimeException("The {$targetId} package has an invalid source locator. No legacy domain records were changed.");
            }
        }

        $legacyPackageSources = $legacyPackages->map(fn (object $package): array => [
            'agovena_id' => (string) $package->agovena_id,
            'composer_name' => $package->composer_name,
            'source_type' => (string) $package->source_type,
            'source_locator' => $this->sanitizeSourceLocator($package->source_locator),
            'version_constraint' => $package->version_constraint,
            'install_path' => $package->install_path,
            'created_at' => $package->created_at,
            'updated_at' => $package->updated_at,
        ])->values()->all();
        $migratedSettings = $this->collectMigratedSettings($legacyIds, $definition);
        $destination = storage_path('app/packages/extensions/'.$targetId);
        $existingTargetPackage = DB::table('agovena_packages')
            ->where('kind', 'extension')
            ->where('agovena_id', $targetId)
            ->first();
        $targetPackageSource = $existingTargetPackage === null
            ? null
            : $this->packageSourceSnapshot($existingTargetPackage);
        $hadCanonicalPackage = $this->hasCanonicalManifest($destination, $definition);
        $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
        if (! $hadCanonicalPackage || File::exists($backup)) {
            $this->newlyMaterializedTargets[$targetId] = [
                'destination_existed' => File::exists($destination) || File::exists($backup),
            ];
        }
        $packagePath = $this->materializeIntegratedPackage($targetId, $definition);
        if ($packagePath === null) {
            throw new RuntimeException("The {$targetId} package is unavailable or invalid. No legacy domain records were changed.");
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
            'legacy_packages' => $legacyPackages,
            'legacy_package' => $legacyPackage,
            'migrated_settings' => $migratedSettings,
            'target_extension' => $targetExtension,
            'target_package' => $targetPackage,
            'legacy_package_sources' => $legacyPackageSources,
            'target_package_source' => $targetPackageSource,
            'source_type' => PackageSourceType::Path->value,
            'source_locator' => $packagePath,
            'package_path' => $packagePath,
            'now' => $migrationNow,
        ];
    }

    /** @param array<string, mixed> $plan */
    private function applyTargetMigration(array $plan): void
    {
        $targetId = $plan['target_id'];
        $definition = $plan['definition'];
        $legacyExtensions = $plan['legacy_extensions'];
        $legacyPackages = $plan['legacy_packages'];
        $legacyPackage = $plan['legacy_package'];
        $targetExtension = $plan['target_extension'];
        $targetPackage = $plan['target_package'];
        $now = $plan['now'];

        $enabled = (bool) ($targetExtension?->enabled ?? false)
            || $legacyExtensions->contains(fn (object $row): bool => (bool) $row->enabled);
        $installedAt = $targetExtension?->installed_at
            ?? $this->earliestTimestamp($legacyExtensions->pluck('installed_at')->all())
            ?? $now;
        $meta = $this->mergedExtensionMetadata(
            $targetExtension,
            $legacyExtensions,
            $plan['legacy_package_sources'],
            $plan['target_package_source'],
            $targetId,
        );
        $enabledAt = $targetExtension?->enabled_at
            ?? $this->earliestTimestamp($legacyExtensions->filter(fn (object $row): bool => (bool) $row->enabled)->pluck('enabled_at')->all())
            ?? $now;
        $disabledAt = $targetExtension?->disabled_at
            ?? $this->earliestTimestamp($legacyExtensions->filter(fn (object $row): bool => ! (bool) $row->enabled)->pluck('disabled_at')->all())
            ?? $now;

        DB::table('agovena_extensions')->updateOrInsert(
            ['extension_id' => $targetId],
            [
                'version' => self::VERSION,
                'enabled' => $enabled,
                'installed_at' => $installedAt,
                'enabled_at' => $enabled ? $enabledAt : null,
                'disabled_at' => $enabled ? null : $disabledAt,
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'created_at' => $targetExtension?->created_at
                    ?? $this->earliestTimestamp($legacyExtensions->pluck('created_at')->all())
                    ?? $now,
                'updated_at' => $now,
            ],
        );

        $packageCreatedAt = $targetPackage?->created_at
            ?? $this->earliestTimestamp($legacyPackages->pluck('created_at')->all())
            ?? $now;

        DB::table('agovena_packages')->updateOrInsert(
            [
                'kind' => 'extension',
                'agovena_id' => $targetId,
            ],
            [
                'composer_name' => null,
                'source_type' => $plan['source_type'],
                'source_locator' => $plan['source_locator'],
                'version_constraint' => $targetPackage?->version_constraint
                    ?? $legacyPackage?->version_constraint
                    ?? '*',
                'installed_version' => self::VERSION,
                'available_version' => self::VERSION,
                'install_path' => $plan['package_path'],
                'is_bundled' => false,
                'created_at' => $packageCreatedAt,
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

    /**
     * @param  iterable<object>  $legacyExtensions
     * @param  list<array<string, mixed>>  $legacyPackageSources
     * @param  array<string, mixed>|null  $targetPackageSource
     * @return array<string, mixed>
     */
    private function mergedExtensionMetadata(
        ?object $targetExtension,
        iterable $legacyExtensions,
        array $legacyPackageSources,
        ?array $targetPackageSource,
        string $targetId,
    ): array {
        $meta = [];
        if ($targetExtension !== null) {
            $meta = $this->mergeMetadata(
                $meta,
                $this->sanitizeMetadata($this->decodeMetadata($targetExtension->meta ?? null, $targetId.' target')),
                $targetId,
            );
        }

        foreach ($legacyExtensions as $extension) {
            $meta = $this->mergeMetadata(
                $meta,
                $this->sanitizeMetadata($this->decodeMetadata($extension->meta ?? null, $targetId.' legacy')),
                $targetId,
            );
        }

        if ($legacyPackageSources === [] && $targetPackageSource === null) {
            return $meta;
        }

        $migration = [];
        if ($legacyPackageSources !== []) {
            $migration['legacy_package_sources'] = $legacyPackageSources;
        }
        if ($targetPackageSource !== null) {
            $migration['target_package_source'] = $targetPackageSource;
        }

        return $this->mergeMetadata($meta, [
            '_agovena_migration' => $migration,
        ], $targetId);
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(mixed $raw, string $source): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            if (array_is_list($raw) && $raw !== []) {
                throw new RuntimeException("The {$source} metadata must be a JSON object.");
            }

            return $raw === [] ? [] : $raw;
        }

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException("The {$source} metadata is invalid JSON.", 0, $exception);
        }

        if (! is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw new RuntimeException("The {$source} metadata must be a JSON object.");
        }

        return $decoded === [] ? [] : $decoded;
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function sanitizeMetadata(array $metadata): array
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizeMetadataValue($metadata);

        return $sanitized;
    }

    private function sanitizeMetadataValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/(?:token|secret|password|passwd|api[_-]?key|access[_-]?key|auth|credential|private[_-]?key)/i', $key) === 1) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[(string) $childKey] = $this->sanitizeMetadataValue($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        return is_string($value) ? $this->sanitizeFreeformProvenance($value) : $value;
    }

    private function sanitizeFreeformProvenance(string $value): string
    {
        $value = preg_replace(
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/is',
            '[REDACTED]',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '#([a-z][a-z0-9+.-]*://)[^/@\s]+(?::[^/@\s]+)?@#i',
            '$1[REDACTED]@',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/(\b(?:authorization|proxy-authorization)\s*:\s*bearer\s+)[^\s]+/i',
            '$1[REDACTED]',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/([?&](?:token|secret|password|passwd|api[_-]?key|access[_-]?key|auth|credential|private[_-]?key)(?:\[[^\]]+\])?=)[^&#\s]*/i',
            '$1[REDACTED]',
            $value,
        ) ?? $value;

        return preg_replace(
            '/((?:token|secret|password|passwd|api[_-]?key|access[_-]?key|auth|credential|private[_-]?key)\s*[:=\s=>]+)("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s,;]+)/i',
            '$1[REDACTED]',
            $value,
        ) ?? $value;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $incoming @return array<string, mixed> */
    private function mergeMetadata(array $base, array $incoming, string $targetId): array
    {
        /** @var array<string, mixed> $merged */
        $merged = $this->mergeMetadataValue($base, $incoming, $targetId);

        return $merged;
    }

    private function mergeMetadataValue(mixed $base, mixed $incoming, string $path): mixed
    {
        if (is_array($base) && is_array($incoming)) {
            if ($base === []) {
                return $incoming;
            }
            if ($incoming === []) {
                return $base;
            }

            $baseList = array_is_list($base);
            $incomingList = array_is_list($incoming);
            if ($baseList || $incomingList) {
                if ($baseList && $incomingList && $this->canonicalize($base) === $this->canonicalize($incoming)) {
                    return $base;
                }

                throw new RuntimeException("The {$path} metadata contains conflicting values.");
            }

            foreach ($incoming as $key => $value) {
                $key = (string) $key;
                if (array_key_exists($key, $base)) {
                    $base[$key] = $this->mergeMetadataValue($base[$key], $value, $path.'.'.$key);
                } else {
                    $base[$key] = $value;
                }
            }

            return $base;
        }

        if ($base !== $incoming) {
            throw new RuntimeException("The {$path} metadata contains conflicting values.");
        }

        return $base;
    }

    private const SAFE_SOURCE_QUERY_KEYS = [
        'ref',
        'branch',
        'tag',
        'version',
        'subdir',
        'path',
        'commit',
        'sha',
    ];

    private function sanitizeSourceLocator(mixed $locator): mixed
    {
        if (! is_string($locator) || trim($locator) === '') {
            return $locator;
        }

        $parts = parse_url($locator);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            if (preg_match('/^[A-Za-z0-9._-]+(?:[\\/][A-Za-z0-9._-]+)*$/', $locator) === 1
                && preg_match('/(?:token|secret|password|passwd|api[_-]?key|access[_-]?key|auth|credential|private[_-]?key)/i', $locator) !== 1
            ) {
                return $locator;
            }

            return '[REDACTED]';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        if (! preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) || $host === '') {
            return '[REDACTED]';
        }
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $sanitized = $scheme.'://'.$host;
        if (isset($parts['port']) && is_int($parts['port']) && $parts['port'] > 0 && $parts['port'] <= 65535) {
            $sanitized .= ':'.$parts['port'];
        }

        if (isset($parts['path']) && $parts['path'] !== '') {
            $path = (string) $parts['path'];
            if (preg_match('/^\/(?:[A-Za-z0-9._~-]+\/)*[A-Za-z0-9._~-]*$/', $path) === 1
                && preg_match('/(?:token|secret|password|passwd|api[_-]?key|access[_-]?key|auth|credential|private[_-]?key)/i', $path) !== 1
            ) {
                $sanitized .= $path;
            }
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $safeQuery = [];
            foreach ($query as $key => $value) {
                $normalizedKey = strtolower((string) $key);
                if (! in_array($normalizedKey, self::SAFE_SOURCE_QUERY_KEYS, true)
                    || ! is_scalar($value)
                ) {
                    continue;
                }
                $safeQuery[$normalizedKey] = (string) $value;
            }
            $queryString = http_build_query($safeQuery, '', '&', PHP_QUERY_RFC3986);
            if ($queryString !== '') {
                $sanitized .= '?'.$queryString;
            }
        }

        return $sanitized;
    }

    /** @return array<string, mixed> */
    private function packageSourceSnapshot(object $package): array
    {
        return [
            'agovena_id' => (string) $package->agovena_id,
            'composer_name' => $package->composer_name,
            'source_type' => (string) $package->source_type,
            'source_locator' => $this->sanitizeSourceLocator($package->source_locator),
            'version_constraint' => $package->version_constraint,
            'install_path' => $package->install_path,
            'installed_version' => $package->installed_version,
            'available_version' => $package->available_version,
            'is_bundled' => (bool) $package->is_bundled,
            'created_at' => $package->created_at,
            'updated_at' => $package->updated_at,
        ];
    }

    private function earliestTimestamp(array $timestamps): mixed
    {
        $indexed = [];
        foreach ($timestamps as $index => $timestamp) {
            if ($timestamp === null || (string) $timestamp === '') {
                continue;
            }

            $parsed = strtotime((string) $timestamp);
            if ($parsed === false) {
                throw new RuntimeException('A legacy domain timestamp is invalid.');
            }
            $indexed[] = [
                'value' => $timestamp,
                'timestamp' => $parsed,
                'index' => $index,
            ];
        }
        if ($indexed === []) {
            return null;
        }

        usort($indexed, static fn (array $left, array $right): int => ($left['timestamp'] <=> $right['timestamp'])
            ?: ($left['index'] <=> $right['index']));

        return $indexed[0]['value'];
    }

    /** @param list<string> $legacyIds @param array<string, mixed> $definition @return array<string, object> */
    private function collectMigratedSettings(array $legacyIds, array $definition): array
    {
        $settings = [];

        foreach (DB::table('extension_settings')
            ->whereIn('extension_id', $legacyIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get() as $setting) {
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

            if (isset($settings[$targetKey])) {
                if ($settings[$targetKey]->value !== $setting->value) {
                    throw new RuntimeException('The legacy Domain provider contains conflicting setting values. No legacy domain records were changed.');
                }

                continue;
            }

            $settings[$targetKey] = $setting;
        }

        return $settings;
    }

    /**
     * @param  'prepared'|'staged'|'backed_up'|'activated'  $phase
     */
    private function writeMaterializationJournal(
        string $targetId,
        string $destination,
        string $staging,
        string $backup,
        string $phase,
        bool $destinationExisted,
    ): void {
        $journal = dirname($destination).DIRECTORY_SEPARATOR.'.'.$targetId.'.materialization.json';
        $temporary = $journal.'.tmp.'.bin2hex(random_bytes(6));
        $json = json_encode([
            'target_id' => $targetId,
            'destination' => $destination,
            'staging' => $staging,
            'backup' => $backup,
            'phase' => $phase,
            'destination_existed' => $destinationExisted,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $handle = @fopen($temporary, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open the {$targetId} materialization journal.");
        }
        try {
            $offset = 0;
            $length = strlen($json);
            while ($offset < $length) {
                $written = fwrite($handle, substr($json, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException("Unable to write the {$targetId} materialization journal.");
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new RuntimeException("Unable to flush the {$targetId} materialization journal.");
            }
            if (function_exists('fsync') && ! fsync($handle)) {
                throw new RuntimeException("Unable to sync the {$targetId} materialization journal.");
            }
        } finally {
            fclose($handle);
        }

        if (! @rename($temporary, $journal)) {
            @unlink($journal);
            if (! @rename($temporary, $journal)) {
                @unlink($temporary);
                throw new RuntimeException("Unable to activate the {$targetId} materialization journal.");
            }
        }
    }

    private function materializeIntegratedPackage(string $targetId, array $definition): ?string
    {
        $extensionRoot = storage_path('app/packages/extensions');
        File::ensureDirectoryExists($extensionRoot);
        $destination = $extensionRoot.DIRECTORY_SEPARATOR.$targetId;
        $staging = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.staging';
        $backup = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.backup';
        $this->assertManagedMigrationPath($destination, $extensionRoot);
        $this->assertManagedMigrationPath($staging, $extensionRoot);
        $this->assertManagedMigrationPath($backup, $extensionRoot);
        if ($this->hasCanonicalManifest($destination, $definition)) {
            $this->assertNoSymlinks($destination);
            $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
            if (File::exists($backup)
                && ! $this->hasLegacyRecords($definition['legacy_ids'])
                && ! $this->deleteFilesystemPath($backup)
            ) {
                throw new RuntimeException("Unable to remove the stale {$targetId} package backup.");
            }

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
        $this->assertNoSymlinks($source);
        $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
        File::ensureDirectoryExists(dirname($destination));
        $hadDestination = File::exists($destination);

        try {
            $this->writeMaterializationJournal($targetId, $destination, $staging, $backup, 'prepared', $hadDestination);
            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                throw new RuntimeException("Unable to clear the staged {$targetId} package.");
            }

            if (File::exists($backup)) {
                if ($this->hasCanonicalManifest($destination, $definition)) {
                    if (! $this->hasLegacyRecords($definition['legacy_ids'])
                        && ! $this->deleteFilesystemPath($backup)
                    ) {
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

            $hadDestination = File::exists($destination);
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

                if (! File::delete($extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.materialization.json')) {
                    throw new RuntimeException("Unable to remove the invalid {$targetId} materialization journal.");
                }

                return null;
            }

            $this->writeMaterializationJournal($targetId, $destination, $staging, $backup, 'staged', $hadDestination);
            if ($hadDestination && ! $this->renameFilesystemPath($destination, $backup)) {
                throw new RuntimeException("Unable to stage the existing {$targetId} package.");
            }
            if ($hadDestination) {
                $this->writeMaterializationJournal($targetId, $destination, $staging, $backup, 'backed_up', $hadDestination);
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

            $this->writeMaterializationJournal($targetId, $destination, $staging, $backup, 'activated', $hadDestination);
            if (! $this->hasCanonicalManifest($destination, $definition)) {
                if (! $this->deleteFilesystemPath($destination)) {
                    throw new RuntimeException("Unable to remove the invalid {$targetId} package destination.");
                }
                if ($hadDestination && File::exists($backup)
                    && ! $this->renameFilesystemPath($backup, $destination)) {
                    throw new RuntimeException("Unable to restore the {$targetId} package after validation failed.");
                }
                if (! File::delete($extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.materialization.json')) {
                    throw new RuntimeException("Unable to remove the invalid {$targetId} materialization journal.");
                }

                return null;
            }
        } catch (Throwable) {
            if (File::exists($staging) && ! $this->deleteFilesystemPath($staging)) {
                throw new RuntimeException("Unable to clean up the staged {$targetId} package.");
            }

            if (File::exists($backup)) {
                if (File::exists($destination) && ! $this->deleteFilesystemPath($destination)) {
                    throw new RuntimeException("Unable to clear the failed {$targetId} package destination.");
                }
                if (! File::exists($destination) && ! $this->renameFilesystemPath($backup, $destination)) {
                    throw new RuntimeException("Unable to restore the {$targetId} package after migration failed.");
                }
            } elseif (! $hadDestination
                && File::exists($destination)
                && ! $this->deleteFilesystemPath($destination)
            ) {
                throw new RuntimeException("Unable to remove the failed {$targetId} package destination.");
            }

            $journal = $extensionRoot.DIRECTORY_SEPARATOR.'.'.$targetId.'.materialization.json';
            if (File::exists($journal) && ! File::delete($journal)) {
                throw new RuntimeException("Unable to remove the failed {$targetId} materialization journal.");
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

    private function assertManagedMigrationPath(string $path, string $root): void
    {
        if (is_link($root) || is_link($path)) {
            throw new RuntimeException('Domain package paths may not use symbolic links.');
        }

        $rootResolved = realpath($root);
        $rootNormalized = $this->normalize($rootResolved ?: $root);
        $pathNormalized = $this->normalize($path);
        if ($pathNormalized === $rootNormalized || ! str_starts_with($pathNormalized, $rootNormalized.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Domain package path is outside the managed root.');
        }

        $resolved = realpath($path);
        if ($resolved !== false) {
            $resolved = $this->normalize($resolved);
            if ($resolved === $rootNormalized || ! str_starts_with($resolved, $rootNormalized.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Domain package path resolves outside the managed root.');
            }

            return;
        }

        $parent = realpath(dirname($path));
        if ($parent === false) {
            throw new RuntimeException('Domain package path parent is unavailable.');
        }
        $parent = $this->normalize($parent);
        if ($parent !== $rootNormalized && ! str_starts_with($parent, $rootNormalized.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Domain package path parent resolves outside the managed root.');
        }
    }

    private function assertNoSymlinks(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Domain package trees may not contain symbolic links.');
        }
        if (! is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                throw new RuntimeException('Domain package trees may not contain symbolic links.');
            }
            if ($entry->isDir()) {
                $this->assertNoSymlinks($entry->getPathname());
            }
        }
    }

    private function deleteFilesystemPath(string $path): bool
    {
        for ($attempt = 0; $attempt < self::FILESYSTEM_RETRY_ATTEMPTS; $attempt++) {
            clearstatcache(true, $path);

            if (is_link($path)) {
                if (@unlink($path) || ! is_link($path)) {
                    return true;
                }
            } elseif (! file_exists($path)) {
                return true;
            } elseif (is_dir($path)) {
                try {
                    $deleted = true;
                    foreach (new DirectoryIterator($path) as $entry) {
                        if ($entry->isDot()) {
                            continue;
                        }
                        if (! $this->deleteFilesystemPath($entry->getPathname())) {
                            $deleted = false;
                            break;
                        }
                    }
                    if ($deleted && (@rmdir($path) || ! file_exists($path))) {
                        return true;
                    }
                } catch (Throwable) {
                    $deleted = false;
                }
            } elseif (@unlink($path) || ! file_exists($path)) {
                return true;
            }

            usleep(self::FILESYSTEM_RETRY_DELAY_MICROSECONDS);
        }

        return false;
    }

    /** @param array<string, mixed> $definition */
    private function hasCanonicalManifest(string $directory, array $definition): bool
    {
        $allowedRoots = [storage_path('app/packages/extensions')];
        $optionalRoot = config('agovena.packages.optional_packages_path');
        if (is_string($optionalRoot) && trim($optionalRoot) !== '') {
            $optionalRoot = realpath($optionalRoot) ?: realpath(base_path(trim($optionalRoot)));
            if ($optionalRoot !== false) {
                $allowedRoots[] = $optionalRoot.DIRECTORY_SEPARATOR.'extensions'.DIRECTORY_SEPARATOR.'domains';
            }
        }
        if (is_link($directory)) {
            return false;
        }

        $resolvedDirectory = realpath($directory);
        if ($resolvedDirectory === false) {
            return false;
        }
        $resolvedDirectory = $this->normalize($resolvedDirectory);
        $allowed = false;
        foreach ($allowedRoots as $allowedRoot) {
            if (is_link($allowedRoot)) {
                continue;
            }
            $resolvedRoot = realpath($allowedRoot);
            if ($resolvedRoot !== false && $this->normalize(dirname($resolvedDirectory)) === $this->normalize($resolvedRoot)) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            return false;
        }
        $directory = $resolvedDirectory;
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'extension.json';
        if (is_link($manifestPath) || ! is_file($manifestPath)) {
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
            $resolvedPath = realpath($path);
            if (is_link($path) || $resolvedPath === false || ! is_file($resolvedPath)) {
                return false;
            }
            $resolvedPath = $this->normalize($resolvedPath);
            if (! str_starts_with($resolvedPath, $directory.DIRECTORY_SEPARATOR)) {
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

    /** @param list<string> $legacyIds */
    private function hasLegacyRecords(array $legacyIds): bool
    {
        return DB::table('agovena_extensions')->whereIn('extension_id', $legacyIds)->exists()
            || DB::table('extension_settings')->whereIn('extension_id', $legacyIds)->exists()
            || DB::table('agovena_packages')->where('kind', 'extension')->whereIn('agovena_id', $legacyIds)->exists();
    }

    private function cleanupOrphanTargetBackups(): void
    {
        foreach ($this->definitions() as $targetId => $definition) {
            if ($this->hasLegacyRecords($definition['legacy_ids'])) {
                continue;
            }
            $destination = storage_path('app/packages/extensions/'.$targetId);
            if (! $this->hasCanonicalManifest($destination, $definition)) {
                continue;
            }

            $backup = storage_path('app/packages/extensions/.'.$targetId.'.backup');
            if (File::exists($backup) && ! $this->deleteFilesystemPath($backup)) {
                throw new RuntimeException("Unable to remove the stale {$targetId} package backup.");
            }
        }
    }

    private function cleanupOrphanLegacyPackageDirectories(): void
    {
        $allowedRoot = realpath(storage_path('app/packages/extensions'));
        if ($allowedRoot === false) {
            return;
        }

        $allowedRoot = $this->normalize($allowedRoot);
        foreach (array_keys($this->legacyToTarget()) as $legacyId) {
            $hasDatabaseRecords = DB::table('agovena_extensions')->where('extension_id', $legacyId)->exists()
                || DB::table('extension_settings')->where('extension_id', $legacyId)->exists()
                || DB::table('agovena_packages')->where('kind', 'extension')->where('agovena_id', $legacyId)->exists();
            if ($hasDatabaseRecords) {
                continue;
            }

            $path = realpath(storage_path('app/packages/extensions/'.$legacyId));
            if ($path === false) {
                continue;
            }

            $path = $this->normalize($path);
            if ($this->normalize(dirname($path)) !== $allowedRoot || basename($path) !== $legacyId) {
                continue;
            }

            if (! $this->deleteFilesystemPath($path)) {
                throw new RuntimeException("Unable to remove the orphan legacy {$legacyId} package directory.");
            }
        }
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

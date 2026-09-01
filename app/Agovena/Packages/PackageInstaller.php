<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Closure;
use Composer\Semver\Comparator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PackageInstaller
{
    public function __construct(
        private readonly PackageSourceValidator $validator,
        private readonly PackageManifestReader $manifests,
        private readonly PackageAutoload $autoload,
        private readonly ComposerRunner $composer,
        private readonly MonorepoCheckout $monorepoCheckout,
        private readonly MonorepoPackageMap $monorepoMap,
        private readonly ZipPackageExtractor $zipExtractor,
        private readonly ModuleManager $modules,
        private readonly ExtensionManager $extensions,
        private readonly ExtensionSettingsRepository $extensionSettings,
        private readonly PackageMigrationRunner $migrations,
    ) {}

    /** @var array<string, array{backup: string|null, staging: string, mode: 'rename'|'copy', journal: string}> */
    private array $materializationStates = [];

    /** @var resource|null */
    private $composerLockHandle = null;

    public function install(PackageSource $source, ?string $expectedAgovenaId = null): AgovenaPackage
    {
        return $this->withPackageLock(
            'global:package-operation',
            fn (): AgovenaPackage => $this->installUnlocked($source, $expectedAgovenaId),
        );
    }

    public function recover(): void
    {
        $this->withPackageLock('global:package-operation', function (): void {
            $this->recoverPendingPackageOperations();
        });
    }

    private function installUnlocked(PackageSource $source, ?string $expectedAgovenaId = null): AgovenaPackage
    {
        $this->validator->assert($source);
        $this->recoverPendingPackageOperations();
        $operationJournal = $this->preparePackageOperationJournal('install', $source->kind, $expectedAgovenaId);
        $composerSnapshot = null;
        $operationCommitted = false;

        try {
            $composerSnapshot = $this->snapshotComposerState($source);
            if ($composerSnapshot !== null) {
                $this->updatePackageOperationJournal($operationJournal, [
                    'composer_state' => $composerSnapshot,
                ]);
            }
            $origin = $this->resolveOrigin($source);

            try {
                $package = $this->installResolved($source, $origin->path, $expectedAgovenaId, $operationJournal);
            } catch (Throwable $exception) {
                if ($origin->cleanupPath !== null) {
                    try {
                        if (! $this->deletePath($origin->cleanupPath)) {
                            $this->queuePendingPackageCleanup($origin->cleanupPath);
                        }
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                        $this->queuePendingPackageCleanup($origin->cleanupPath);
                    }
                }

                throw $exception;
            }

            $this->markPackageOperationCommitted($operationJournal);
            $operationCommitted = true;
            $this->finalizeCommittedPackageOperation($operationJournal);
            if ($origin->cleanupPath !== null) {
                $this->deferCommittedCleanup($origin->cleanupPath);
            }

            return $package;
        } catch (Throwable $exception) {
            if (! $operationCommitted) {
                try {
                    $this->recoverPackageOperationJournal($operationJournal);
                } catch (Throwable $rollbackException) {
                    throw new \RuntimeException('Package installation failed and durable recovery did not complete.', 0, $rollbackException);
                }
            }

            throw $exception;
        } finally {
            if ($composerSnapshot !== null) {
                $this->releaseComposerLock();
            }
        }
    }

    private function deferCommittedCleanup(string $path): void
    {
        try {
            if (! $this->deletePath($path)) {
                $this->queuePendingPackageCleanup($path);
            }
        } catch (Throwable $cleanupException) {
            report($cleanupException);

            try {
                $this->queuePendingPackageCleanup($path);
            } catch (Throwable $journalException) {
                report($journalException);
                Log::critical('Committed package cleanup could not be journaled.', [
                    'exception' => $journalException::class,
                    'phase' => 'post_commit_cleanup',
                ]);

                throw new \RuntimeException(
                    'Committed package cleanup could not be journaled.',
                    previous: $journalException,
                );
            }
        }
    }

    private function installResolved(
        PackageSource $source,
        string $origin,
        ?string $expectedAgovenaId = null,
        ?string $operationJournal = null,
    ): AgovenaPackage {
        $manifest = $this->manifests->read($origin);
        $this->validator->assertKind($source->kind, $manifest['kind']);
        $this->validator->assertAgovenaId($manifest['id']);

        if ($expectedAgovenaId !== null && $manifest['id'] !== $expectedAgovenaId) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.package_id_mismatch', [
                    'expected' => $expectedAgovenaId,
                    'actual' => $manifest['id'],
                ]),
            ]);
        }

        $existingPackage = AgovenaPackage::query()
            ->where('kind', $manifest['kind'])
            ->where('agovena_id', $manifest['id'])
            ->first();
        $previousPackage = $existingPackage?->getAttributes();
        $previousLifecycle = $this->lifecycleAttributes($manifest['kind'], $manifest['id']);
        $canonicalDestination = $this->installRoot($manifest['kind']).DIRECTORY_SEPARATOR.$manifest['id'];
        $this->assertExistingInstallPath($existingPackage?->install_path, $canonicalDestination);
        $previousFingerprint = $this->packageTreeFingerprint($canonicalDestination);
        if ($operationJournal !== null) {
            $this->updatePackageOperationJournal($operationJournal, [
                'kind' => $manifest['kind']->value,
                'agovena_id' => $manifest['id'],
                'destination' => $this->installRoot($manifest['kind']).DIRECTORY_SEPARATOR.$manifest['id'],
                'previous_fingerprint' => $previousFingerprint,
                'previous_state' => Crypt::encryptString(json_encode([
                    'package' => $previousPackage,
                    'lifecycle' => $previousLifecycle,
                    'extension_settings' => $manifest['kind'] === PackageKind::Extension
                        ? $this->extensionSettings->snapshot($manifest['id'])
                        : null,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ]);
        }
        $destination = null;
        $package = null;
        $migrationJournal = null;

        try {
            $destination = $this->materialize(
                $origin,
                $manifest['kind'],
                $manifest['id'],
                $manifest,
                $previousPackage,
                $previousLifecycle,
                $previousFingerprint,
            );
            if ($operationJournal !== null) {
                $this->updatePackageOperationJournal($operationJournal, [
                    'materialization_journal' => $this->materializationStates[$destination]['journal'] ?? null,
                ]);
            }
            $this->autoload->register($destination, $manifest['autoload']);
            $this->refreshManagers();

            $package = AgovenaPackage::query()->firstOrNew([
                'kind' => $manifest['kind'],
                'agovena_id' => $manifest['id'],
            ]);
            $package->composer_name = match ($source->sourceType) {
                PackageSourceType::Monorepo => $source->composerName,
                default => $source->composerName ?? ($source->sourceType === PackageSourceType::Composer ? $source->locator : $package->composer_name),
            };
            $package->source_type = $source->sourceType;
            $package->source_locator = $this->resolvedSourceLocator($source);
            $package->version_constraint = $source->constraint;
            $package->installed_version = $manifest['version'];
            $package->available_version = $manifest['version'];
            $package->install_path = $destination;
            $package->is_bundled = false;
            $package->save();

            $migrationJournal = $this->migrations->prepare($manifest['id'], $destination);
            if ($operationJournal !== null) {
                $this->updatePackageOperationJournal($operationJournal, [
                    'migration_journal' => $migrationJournal,
                ]);
            }

            if ($manifest['kind'] === PackageKind::Module) {
                $this->modules->install($manifest['id'], $migrationJournal);
            } else {
                $this->extensions->install($manifest['id'], $migrationJournal);
            }

            $this->restoreEnabledState($manifest['kind'], $manifest['id'], $previousLifecycle);
        } catch (Throwable $exception) {
            if ($migrationJournal !== null && File::exists($migrationJournal)) {
                try {
                    $this->migrations->rollback($migrationJournal);
                } catch (Throwable $migrationRollbackException) {
                    throw new \RuntimeException(
                        'Package migrations could not be rolled back before package recovery.',
                        0,
                        $migrationRollbackException,
                    );
                }
            }

            $rollbackFailure = $this->rollbackFailedInstall(
                $destination,
                $manifest['kind'],
                $manifest['id'],
                $previousPackage,
                $previousLifecycle,
                $previousFingerprint,
                $exception,
            );
            if ($rollbackFailure !== null) {
                throw $rollbackFailure;
            }

            throw $exception;
        }

        return $package->fresh() ?? $package;
    }

    public function update(PackageKind $kind, string $agovenaId): AgovenaPackage
    {
        return $this->withPackageLock('global:package-operation', function () use ($kind, $agovenaId): AgovenaPackage {
            $this->recoverPendingPackageOperations();
            $package = $this->requirePackage($kind, $agovenaId);
            if ($package->is_bundled || $package->source_type === PackageSourceType::Bundled) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.cannot_update_bundled'),
                ]);
            }
            if ($package->source_type === PackageSourceType::Zip) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.cannot_update_zip'),
                ]);
            }

            $source = new PackageSource(
                kind: $package->kind,
                sourceType: $package->source_type,
                locator: (string) $package->source_locator,
                constraint: $package->version_constraint,
                composerName: $package->composer_name,
            );

            return $this->installUnlocked($source, expectedAgovenaId: $agovenaId);
        });
    }

    /**
     * Remove from runtime. Data/settings are preserved. Remote files stay on disk unless $purgeFiles.
     */
    public function uninstall(PackageKind $kind, string $agovenaId, bool $purgeFiles = false): void
    {
        if ($purgeFiles) {
            $this->purge($kind, $agovenaId);

            return;
        }

        $this->withPackageLock('global:package-operation', function () use ($kind, $agovenaId): void {
            $this->recoverPendingPackageOperations();
            $this->uninstallUnlocked($kind, $agovenaId, purgeFiles: false);
        });
    }

    private function uninstallUnlocked(PackageKind $kind, string $agovenaId, bool $purgeFiles = false): void
    {
        if ($kind === PackageKind::Module) {
            $this->modules->uninstall($agovenaId, purgeData: false);
        } else {
            $this->extensions->uninstall($agovenaId, purgeData: false);
        }

        $package = AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $agovenaId)
            ->first();

        if ($package === null) {
            return;
        }

        if ($package->is_bundled) {
            return;
        }

        if ($purgeFiles) {
            $this->purgeFiles($package);
            $package->delete();
            $this->refreshManagers();
        }
    }

    /**
     * Uninstall and delete materialized remote files. Does not drop Module tables.
     */
    public function purge(PackageKind $kind, string $agovenaId): void
    {
        $this->withPackageLock('global:package-operation', function () use ($kind, $agovenaId): void {
            $this->purgeUnlocked($kind, $agovenaId);
        });
    }

    private function purgeUnlocked(PackageKind $kind, string $agovenaId): void
    {
        $this->recoverPendingPackageOperations();
        $package = AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $agovenaId)
            ->first();

        if ($package === null || $package->is_bundled) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.cannot_purge_bundled'),
            ]);
        }

        $previousPackage = $package->getAttributes();
        $previousLifecycle = $this->lifecycleAttributes($kind, $agovenaId);
        $previousFingerprint = $this->packageTreeFingerprint($package->install_path ?? '');
        $treeSnapshot = $this->planPackageTreeSnapshot($package);
        $operationJournal = $this->preparePackageOperationJournal('purge', $kind, $agovenaId);
        $this->updatePackageOperationJournal($operationJournal, [
            'destination' => $this->installRoot($kind).DIRECTORY_SEPARATOR.$agovenaId,
            'previous_fingerprint' => $previousFingerprint,
            'previous_state' => Crypt::encryptString(json_encode([
                'package' => $previousPackage,
                'lifecycle' => $previousLifecycle,
                'extension_settings' => $kind === PackageKind::Extension
                    ? $this->extensionSettings->snapshot($agovenaId)
                    : null,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ]);
        $composerSnapshot = null;
        $purgeJournal = null;
        $operationCommitted = false;

        try {
            $composerSnapshot = $this->snapshotComposerStateForPackage($package);
            if ($composerSnapshot !== null) {
                $this->updatePackageOperationJournal($operationJournal, [
                    'composer_state' => $composerSnapshot,
                ]);
            }
            $purgeJournal = $this->preparePurgeJournal(
                $treeSnapshot,
                $kind,
                $agovenaId,
                $previousPackage,
                $previousLifecycle,
                $previousFingerprint,
            );
            $this->updatePackageOperationJournal($operationJournal, [
                'purge_journal' => $purgeJournal,
            ]);
            if ($treeSnapshot !== null && ! $this->copyDirectoryExactly($treeSnapshot['destination'], $treeSnapshot['snapshot'])) {
                throw new \RuntimeException('Unable to snapshot the package files.');
            }
            $this->setPurgeJournalSnapshotReady($purgeJournal);
            $this->uninstallUnlocked($kind, $agovenaId, purgeFiles: true);
            $this->markPackageOperationCommitted($operationJournal);
            $operationCommitted = true;
            $this->finalizeCommittedPackageOperation($operationJournal);
        } catch (Throwable $exception) {
            if (! $operationCommitted) {
                try {
                    $this->recoverPackageOperationJournal($operationJournal);
                } catch (Throwable $rollbackException) {
                    throw new \RuntimeException('Package purge failed and durable recovery did not complete.', 0, $rollbackException);
                }
            }

            throw $exception;
        } finally {
            if ($composerSnapshot !== null) {
                $this->releaseComposerLock();
            }
        }
    }

    public function hasUpdate(AgovenaPackage $package): bool
    {
        if ($package->source_type === PackageSourceType::Zip
            || $package->available_version === null
            || $package->installed_version === null
        ) {
            return false;
        }

        try {
            return Comparator::greaterThan($package->available_version, $package->installed_version);
        } catch (Throwable) {
            return false;
        }
    }

    private function packageOperationJournalRoot(): string
    {
        $packagesRoot = storage_path('app/packages');
        $journalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.package-operations';
        File::ensureDirectoryExists($journalRoot);
        if (is_link($journalRoot)) {
            throw new \RuntimeException('Package operation journal root may not use symbolic links.');
        }

        return $journalRoot;
    }

    private function preparePackageOperationJournal(string $operation, PackageKind $kind, ?string $agovenaId): string
    {
        if (! in_array($operation, ['install', 'purge'], true)) {
            throw new \RuntimeException('Package operation is invalid.');
        }

        $journalRoot = $this->packageOperationJournalRoot();
        $journal = $journalRoot.DIRECTORY_SEPARATOR.'package-'.bin2hex(random_bytes(12)).'.json';
        $this->writeAtomicJson($journal, [
            'status' => 'prepared',
            'operation' => $operation,
            'kind' => $kind->value,
            'agovena_id' => $agovenaId,
            'destination' => null,
            'previous_fingerprint' => null,
            'previous_state' => null,
            'composer_state' => null,
            'migration_journal' => null,
            'materialization_journal' => null,
            'purge_journal' => null,
        ], $journalRoot);

        return $journal;
    }

    /** @param array<string, mixed> $changes */
    private function updatePackageOperationJournal(string $journal, array $changes): void
    {
        $this->withPackageLock('global:package-operation-journal', function () use ($journal, $changes): void {
            $journalRoot = $this->packageOperationJournalRoot();
            $this->assertManagedPath($journal, $journalRoot);
            $payload = $this->readPackageOperationJournal($journal);
            $allowed = [
                'status',
                'kind',
                'agovena_id',
                'destination',
                'previous_fingerprint',
                'previous_state',
                'composer_state',
                'migration_journal',
                'materialization_journal',
                'purge_journal',
            ];
            foreach ($changes as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    throw new \RuntimeException('Package operation journal field is invalid.');
                }
                $payload[$key] = $value;
            }
            if (! in_array($payload['status'] ?? null, ['prepared', 'committed'], true)) {
                throw new \RuntimeException('Package operation journal status is invalid.');
            }
            $this->writeAtomicJson($journal, $payload, $journalRoot);
        });
    }

    private function markPackageOperationCommitted(string $journal): void
    {
        $this->updatePackageOperationJournal($journal, ['status' => 'committed']);
    }

    private function deletePackageOperationJournal(string $journal): void
    {
        $this->withPackageLock('global:package-operation-journal', function () use ($journal): void {
            $journalRoot = $this->packageOperationJournalRoot();
            $this->assertManagedPath($journal, $journalRoot);
            if (File::exists($journal) && ! $this->deletePath($journal)) {
                throw new \RuntimeException('Package operation journal could not be removed.');
            }
        });
    }

    private function recoverPendingPackageOperations(): void
    {
        $this->cleanupPendingPackageArtifacts();
        $this->reconcilePackageOperationJournals();
        $this->migrations->reconcile();
        $this->reconcilePurgeJournals();
        $this->reconcileMaterializationJournals();
        $this->reconcileStandaloneComposerOperation();
    }

    private function reconcilePackageOperationJournals(): void
    {
        $journalRoot = $this->packageOperationJournalRoot();
        $journals = $this->withPackageLock(
            'global:package-operation-journal-list',
            fn (): array => glob($journalRoot.DIRECTORY_SEPARATOR.'package-*.json') ?: [],
        );

        foreach ($journals as $journal) {
            $this->assertManagedPath($journal, $journalRoot);
            $this->reconcilePackageOperationJournal($journal);
        }
    }

    private function recoverPackageOperationJournal(string $journal): void
    {
        if (! File::exists($journal)) {
            return;
        }

        $this->reconcilePackageOperationJournal($journal);
    }

    private function reconcilePackageOperationJournal(string $journal): void
    {
        $payload = $this->readPackageOperationJournal($journal);
        if ($payload['status'] === 'committed') {
            $this->finalizeCommittedPackageOperation($journal, $payload);

            return;
        }

        $this->recoverPreparedPackageOperation($journal, $payload);
    }

    /** @return array<string, mixed> */
    private function readPackageOperationJournal(string $journal): array
    {
        $journalRoot = $this->packageOperationJournalRoot();
        $this->assertManagedPath($journal, $journalRoot);
        $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ! array_key_exists('status', $payload)
            || ! array_key_exists('operation', $payload)
            || ! array_key_exists('kind', $payload)
            || ! array_key_exists('agovena_id', $payload)
            || ! array_key_exists('destination', $payload)
            || ! array_key_exists('previous_fingerprint', $payload)
            || ! array_key_exists('previous_state', $payload)
            || ! array_key_exists('composer_state', $payload)
            || ! array_key_exists('migration_journal', $payload)
            || ! array_key_exists('materialization_journal', $payload)
            || ! array_key_exists('purge_journal', $payload)
            || ! in_array($payload['status'], ['prepared', 'committed'], true)
            || ! in_array($payload['operation'], ['install', 'purge'], true)
            || ! is_string($payload['kind'])
            || PackageKind::tryFrom($payload['kind']) === null
            || ($payload['agovena_id'] !== null && (! is_string($payload['agovena_id']) || $payload['agovena_id'] === ''))
            || ($payload['destination'] !== null && ! is_string($payload['destination']))
            || ($payload['previous_fingerprint'] !== null && ! is_string($payload['previous_fingerprint']))
            || ($payload['previous_state'] !== null && ! is_string($payload['previous_state']))
            || ($payload['migration_journal'] !== null && ! is_string($payload['migration_journal']))
            || ($payload['materialization_journal'] !== null && ! is_string($payload['materialization_journal']))
            || ($payload['purge_journal'] !== null && ! is_string($payload['purge_journal']))
            || ($payload['composer_state'] !== null && ! is_array($payload['composer_state']))
        ) {
            throw new \RuntimeException('Package operation journal is invalid.');
        }

        $composerState = $payload['composer_state'];
        if ($composerState !== null
            && (! is_string($composerState['root'] ?? null)
                || ! is_string($composerState['snapshot'] ?? null)
                || ! is_string($composerState['marker'] ?? null)
                || ! is_bool($composerState['existed'] ?? null))
        ) {
            throw new \RuntimeException('Package operation Composer state is invalid.');
        }
        if ($composerState !== null) {
            $packagesRoot = storage_path('app/packages');
            if ($this->normalize($composerState['root']) !== $this->normalize($packagesRoot.DIRECTORY_SEPARATOR.'composer')
                || $this->normalize($composerState['marker']) !== $this->normalize($packagesRoot.DIRECTORY_SEPARATOR.'.composer-operation.json')
                || ! preg_match('/^\.composer\.[A-Za-z0-9_-]+\.snapshot$/', basename($composerState['snapshot']))
            ) {
                throw new \RuntimeException('Package operation Composer paths are not canonical.');
            }
        }

        $agovenaId = $payload['agovena_id'];
        $destination = $payload['destination'];
        if ($agovenaId !== null && $destination !== null) {
            $kind = PackageKind::from($payload['kind']);
            $canonical = $this->installRoot($kind).DIRECTORY_SEPARATOR.$agovenaId;
            $this->assertManagedPath($canonical, $this->installRoot($kind));
            if ($this->normalize($payload['destination']) !== $this->normalize($canonical)) {
                throw new \RuntimeException('Package operation destination is not canonical.');
            }
        }

        foreach (['migration_journal', 'materialization_journal', 'purge_journal'] as $component) {
            if ($payload[$component] !== null) {
                $this->assertManagedPath(
                    $payload[$component],
                    match ($component) {
                        'migration_journal' => storage_path('app/packages').DIRECTORY_SEPARATOR.'.migration-operations',
                        'materialization_journal' => $this->materializationJournalRoot(),
                        default => storage_path('app/packages').DIRECTORY_SEPARATOR.'.purge-operations',
                    },
                );
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function recoverPreparedPackageOperation(string $journal, array $payload): void
    {
        $composerState = $payload['composer_state'];
        if ($composerState !== null) {
            $this->assertManagedPath($composerState['root'], storage_path('app/packages'));
            $this->assertManagedPath($composerState['snapshot'], storage_path('app/packages'));
            $this->assertManagedPath($composerState['marker'], storage_path('app/packages'));
            $this->restoreComposerState($composerState);
            if (! $this->forgetComposerSnapshot($composerState)) {
                throw new \RuntimeException('Prepared package operation Composer recovery could not be finalized.');
            }
        }

        if ($payload['migration_journal'] !== null && File::exists($payload['migration_journal'])) {
            $this->migrations->rollback($payload['migration_journal']);
        }

        $this->reconcilePurgeJournals();
        $this->reconcileMaterializationJournals();

        $agovenaId = $payload['agovena_id'];
        if ($agovenaId !== null && $payload['previous_state'] !== null) {
            $previousState = $this->decryptPackageOperationState($payload['previous_state']);
            $kind = PackageKind::from($payload['kind']);
            $this->restorePackage($kind, $agovenaId, $previousState['package']);
            $this->restoreLifecycle($kind, $agovenaId, $previousState['lifecycle']);
            $this->extensionSettings->restore($agovenaId, $previousState['extension_settings'] ?? []);
            $this->verifyRestoredState(
                $kind,
                $agovenaId,
                $previousState['package'],
                $previousState['lifecycle'],
                $payload['previous_fingerprint'],
            );
            $this->refreshManagers();
        }

        $this->deletePackageOperationJournal($journal);
    }

    /** @return array{package: array<string, mixed>|null, lifecycle: array<string, mixed>|null, extension_settings: list<mixed>|null} */
    private function decryptPackageOperationState(string $encrypted): array
    {
        $state = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($state)
            || ! array_key_exists('package', $state)
            || ($state['package'] !== null && ! is_array($state['package']))
            || ! array_key_exists('lifecycle', $state)
            || ($state['lifecycle'] !== null && ! is_array($state['lifecycle']))
        ) {
            throw new \RuntimeException('Package operation previous state is invalid.');
        }

        $extensionSettings = $state['extension_settings'] ?? null;
        if ($extensionSettings !== null
            && (! is_array($extensionSettings) || ! array_is_list($extensionSettings))
        ) {
            throw new \RuntimeException('Package operation extension settings state is invalid.');
        }

        return [
            'package' => $state['package'],
            'lifecycle' => $state['lifecycle'],
            'extension_settings' => $extensionSettings,
        ];
    }

    /** @param array<string, mixed>|null $payload */
    private function finalizeCommittedPackageOperation(string $journal, ?array $payload = null): void
    {
        $payload ??= $this->readPackageOperationJournal($journal);
        if ($payload['status'] !== 'committed') {
            throw new \RuntimeException('Package operation is not committed.');
        }

        $composerState = $payload['composer_state'];
        if ($composerState !== null) {
            $this->assertManagedPath($composerState['root'], storage_path('app/packages'));
            $this->assertManagedPath($composerState['snapshot'], storage_path('app/packages'));
            $this->assertManagedPath($composerState['marker'], storage_path('app/packages'));
            if (File::exists($composerState['marker']) || File::exists($composerState['snapshot'])) {
                $this->markComposerOperationCommitted($composerState);
                if (! $this->forgetComposerSnapshot($composerState)) {
                    throw new \RuntimeException('Committed package operation Composer cleanup did not complete.');
                }
            }
        }

        if ($payload['migration_journal'] !== null && File::exists($payload['migration_journal'])) {
            $this->migrations->commit($payload['migration_journal']);
        }
        $this->migrations->reconcile();

        if ($payload['materialization_journal'] !== null && File::exists($payload['materialization_journal'])) {
            $this->updateMaterializationJournal($payload['materialization_journal'], ['status' => 'committed']);
        }
        if ($payload['purge_journal'] !== null && File::exists($payload['purge_journal'])) {
            $this->setPurgeJournalStatus($payload['purge_journal'], 'committed');
        }
        $this->reconcileMaterializationJournals();
        $this->reconcilePurgeJournals();
        if (is_string($payload['destination'])) {
            unset($this->materializationStates[$payload['destination']]);
        }
        $this->deletePackageOperationJournal($journal);
    }

    private function reconcileStandaloneComposerOperation(): void
    {
        $packagesRoot = storage_path('app/packages');
        $marker = $packagesRoot.DIRECTORY_SEPARATOR.'.composer-operation.json';
        if (! File::exists($marker)) {
            return;
        }

        $this->acquireComposerLock($packagesRoot);
        try {
            $this->reconcileComposerOperation($marker, $packagesRoot);
        } finally {
            $this->releaseComposerLock();
        }
    }

    /** @return array{root: string, snapshot: string, marker: string, existed: bool}|null */
    private function snapshotComposerState(PackageSource $source): ?array
    {
        if (! in_array($source->sourceType, [PackageSourceType::Composer, PackageSourceType::Vcs], true)) {
            return null;
        }

        $packagesRoot = storage_path('app/packages');
        $root = $packagesRoot.DIRECTORY_SEPARATOR.'composer';
        $snapshot = $packagesRoot.DIRECTORY_SEPARATOR.'.composer.'.bin2hex(random_bytes(12)).'.snapshot';
        $marker = $packagesRoot.DIRECTORY_SEPARATOR.'.composer-operation.json';
        File::ensureDirectoryExists($packagesRoot);
        if (is_link($packagesRoot)) {
            throw new \RuntimeException('Composer package root may not use symbolic links.');
        }
        $this->assertManagedPath($root, $packagesRoot);
        $this->assertManagedPath($snapshot, $packagesRoot);
        $this->assertManagedPath($marker, $packagesRoot);
        $this->acquireComposerLock($packagesRoot);

        try {
            $this->reconcileComposerOperation($marker, $packagesRoot);

            $existed = File::exists($root);
            if ($existed) {
                if (! is_dir($root)) {
                    throw new \RuntimeException('Composer package root is not a directory.');
                }
                $this->assertNoSymlinks($root);
            }

            $state = [
                'root' => $root,
                'snapshot' => $snapshot,
                'marker' => $marker,
                'existed' => $existed,
            ];
            $this->writeComposerOperation($state, 'preparing');
            if ($existed && ! $this->copyDirectoryExactly($root, $snapshot)) {
                throw new \RuntimeException('Unable to snapshot the Composer package state.');
            }
            $this->writeComposerOperation($state, 'ready');

            return $state;
        } catch (Throwable $exception) {
            try {
                if (File::exists($snapshot) && ! $this->deletePath($snapshot)) {
                    report(new \RuntimeException('Composer snapshot cleanup was deferred.'));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
            try {
                if (File::exists($marker) && ! $this->deletePath($marker)) {
                    report(new \RuntimeException('Composer journal cleanup was deferred.'));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
            $this->releaseComposerLock();
            throw $exception;
        }
    }

    /** @param array{root: string, snapshot: string, marker: string, existed: bool} $state */
    private function restoreComposerState(array $state): void
    {
        $packagesRoot = storage_path('app/packages');
        $this->assertManagedPath($state['root'], $packagesRoot);
        $this->assertManagedPath($state['snapshot'], $packagesRoot);

        if (File::exists($state['root']) && ! $this->deletePath($state['root'])) {
            throw new \RuntimeException('Unable to clear the Composer package state.');
        }

        if ($state['existed'] && ! $this->copyDirectoryExactly($state['snapshot'], $state['root'])) {
            throw new \RuntimeException('Unable to restore the Composer package state.');
        }
    }

    /** @param array{root: string, snapshot: string, marker: string, existed: bool} $state */
    private function writeComposerOperation(array $state, string $status): void
    {
        $payload = [
            'root' => $state['root'],
            'snapshot' => $state['snapshot'],
            'existed' => $state['existed'],
            'status' => $status,
        ];
        $this->writeAtomicJson($state['marker'], $payload, storage_path('app/packages'));
    }

    /** @param array{root: string, snapshot: string, marker: string, existed: bool} $state */
    private function markComposerOperationCommitted(array $state): void
    {
        $this->writeComposerOperation($state, 'committed');
    }

    /** @param array{root: string, snapshot: string, marker: string, existed: bool} $state */
    private function forgetComposerSnapshot(array $state): bool
    {
        $snapshotRemoved = ! File::exists($state['snapshot']) || $this->deletePath($state['snapshot']);
        $markerRemoved = ! File::exists($state['marker']) || $this->deletePath($state['marker']);

        return $snapshotRemoved && $markerRemoved;
    }

    private function reconcileComposerOperation(string $marker, string $packagesRoot): void
    {
        if (! File::exists($marker)) {
            $this->cleanupOrphanComposerSnapshots($packagesRoot);

            return;
        }

        /** @var array<string, mixed>|null $operation */
        $operation = json_decode((string) File::get($marker), true);
        if (! is_array($operation)
            || ! is_string($operation['root'] ?? null)
            || ! is_string($operation['snapshot'] ?? null)
            || ! in_array($operation['status'] ?? null, ['preparing', 'ready', 'committed'], true)
            || ! is_bool($operation['existed'] ?? null)
        ) {
            throw new \RuntimeException('Composer operation journal is invalid.');
        }

        $this->assertManagedPath($operation['root'], $packagesRoot);
        $this->assertManagedPath($operation['snapshot'], $packagesRoot);
        $this->assertManagedPath($marker, $packagesRoot);
        $canonicalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'composer';
        if ($this->normalize($operation['root']) !== $this->normalize($canonicalRoot)
            || ! preg_match('/^\.composer\.[A-Za-z0-9_-]+\.snapshot$/', basename($operation['snapshot']))
            || $this->normalize($marker) !== $this->normalize($packagesRoot.DIRECTORY_SEPARATOR.'.composer-operation.json')
        ) {
            throw new \RuntimeException('Composer operation journal paths are not canonical.');
        }
        $state = [
            'root' => $operation['root'],
            'snapshot' => $operation['snapshot'],
            'marker' => $marker,
            'existed' => $operation['existed'],
        ];

        if ($operation['status'] === 'preparing') {
            if (! $this->forgetComposerSnapshot($state)) {
                throw new \RuntimeException('Unable to remove an incomplete Composer operation journal.');
            }
            $this->cleanupOrphanComposerSnapshots($packagesRoot);

            return;
        }
        if ($operation['status'] === 'committed') {
            if (! $this->forgetComposerSnapshot($state)) {
                throw new \RuntimeException('Unable to finalize the Composer operation journal.');
            }
            $this->cleanupOrphanComposerSnapshots($packagesRoot);

            return;
        }

        if ($state['existed']) {
            if (File::exists($state['root']) && ! $this->deletePath($state['root'])) {
                throw new \RuntimeException('Unable to clear the interrupted Composer package state.');
            }
            if (! File::exists($state['snapshot']) || ! $this->copyDirectoryExactly($state['snapshot'], $state['root'])) {
                throw new \RuntimeException('Unable to recover the interrupted Composer operation.');
            }
        } elseif (File::exists($state['root']) && ! $this->deletePath($state['root'])) {
            throw new \RuntimeException('Unable to remove the interrupted Composer package state.');
        }

        if (! $this->forgetComposerSnapshot($state)) {
            throw new \RuntimeException('Unable to finalize the interrupted Composer recovery.');
        }
        $this->cleanupOrphanComposerSnapshots($packagesRoot);
    }

    private function cleanupOrphanComposerSnapshots(string $packagesRoot): void
    {
        foreach (glob($packagesRoot.DIRECTORY_SEPARATOR.'.composer.*.snapshot') ?: [] as $snapshot) {
            $this->assertManagedPath($snapshot, $packagesRoot);
            if (! $this->deletePath($snapshot)) {
                throw new \RuntimeException('Unable to remove an orphaned Composer snapshot.');
            }
        }
    }

    private function acquireComposerLock(string $packagesRoot): void
    {
        if ($this->composerLockHandle !== null) {
            return;
        }

        $lockPath = $packagesRoot.DIRECTORY_SEPARATOR.'.composer.operation.lock';
        $this->assertManagedPath($lockPath, $packagesRoot);
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false || ! @flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException('Unable to lock the Composer package operation.');
        }
        $this->composerLockHandle = $handle;
    }

    private function releaseComposerLock(): void
    {
        if (! is_resource($this->composerLockHandle)) {
            $this->composerLockHandle = null;

            return;
        }

        @flock($this->composerLockHandle, LOCK_UN);
        @fclose($this->composerLockHandle);
        $this->composerLockHandle = null;
    }

    private function resolveOrigin(PackageSource $source): ResolvedPackageOrigin
    {
        return match ($source->sourceType) {
            PackageSourceType::Path => new ResolvedPackageOrigin($this->validator->assertPath($source->locator)),
            PackageSourceType::Zip => $this->zipExtractor->extract($source->locator, $source->kind),
            PackageSourceType::Composer => new ResolvedPackageOrigin($this->composer->require(
                $source->composerName ?? $source->locator,
                $source->constraint,
            )->path),
            PackageSourceType::Vcs => new ResolvedPackageOrigin($this->composer->require(
                (string) $source->composerName,
                $source->constraint,
                $source->locator,
            )->path),
            PackageSourceType::Monorepo => new ResolvedPackageOrigin($this->resolveMonorepoOrigin($source)),
            PackageSourceType::Bundled => throw ValidationException::withMessages([
                'package' => __('admin.packages.bundled_use_lifecycle'),
            ]),
        };
    }

    /** @param array<string, mixed> $manifest */
    /** @param array<string, mixed>|null $previousPackage @param array<string, mixed>|null $previousLifecycle */
    private function materialize(
        string $origin,
        PackageKind $kind,
        string $id,
        array $manifest,
        ?array $previousPackage,
        ?array $previousLifecycle,
        ?string $previousFingerprint,
    ): string {
        $root = $this->installRoot($kind);
        File::ensureDirectoryExists($root);
        $this->assertManagedPath($root.DIRECTORY_SEPARATOR.$id, $root);

        $destination = $root.DIRECTORY_SEPARATOR.$id;

        $originNormalized = $this->normalize($origin);
        $destinationNormalized = $this->normalize($destination);
        if ($originNormalized === $destinationNormalized) {
            $this->assertNoSymlinks($origin);
            $this->assertNoSymlinks($destination);

            return $destination;
        }

        $staging = $root.DIRECTORY_SEPARATOR.'.'.$id.'.staging';
        $backup = $root.DIRECTORY_SEPARATOR.'.'.$id.'.backup';
        $this->assertManagedPath($staging, $root);
        $this->assertManagedPath($backup, $root);
        $this->assertNoSymlinks($origin);
        if (File::exists($destination)) {
            $this->assertNoSymlinks($destination);
        }
        if (File::exists($staging)) {
            $this->assertNoSymlinks($staging);
        }
        if (File::exists($backup)) {
            $this->assertNoSymlinks($backup);
        }
        $hadDestination = File::exists($destination);
        $destinationBackedUp = false;
        $backupMode = 'rename';
        $destinationActivated = false;
        $journal = null;

        try {
            if (File::exists($staging) && ! $this->deletePath($staging)) {
                throw new \RuntimeException("Unable to clear the staged {$id} package.");
            }

            if (File::exists($backup)) {
                $this->recoverInterruptedMaterialization($destination, $backup, $kind, $id);
                $hadDestination = File::exists($destination);
            }

            $journal = $this->prepareMaterializationJournal(
                $kind,
                $id,
                $destination,
                $staging,
                $backup,
                $hadDestination,
                $previousFingerprint,
                $previousPackage,
                $previousLifecycle,
            );

            if (! File::copyDirectory($origin, $staging)
                || ! is_dir($staging)
                || ! $this->materializedManifestMatches($staging, $manifest)
            ) {
                throw new \RuntimeException("Unable to validate the staged {$id} package.");
            }

            if (File::exists($destination)) {
                if (! $this->renamePath($destination, $backup)) {
                    if (! $this->copyDirectoryExactly($destination, $backup) || ! is_dir($backup)) {
                        throw new \RuntimeException("Unable to back up the existing {$id} package.");
                    }
                    $backupMode = 'copy';
                }
                $destinationBackedUp = true;
                $this->updateMaterializationJournal($journal, [
                    'backup_mode' => $backupMode,
                    'destination_backed_up' => true,
                ]);
            }

            if ($backupMode === 'copy') {
                if (! $this->copyDirectoryExactly($staging, $destination)
                    || ! $this->materializedManifestMatches($destination, $manifest)
                ) {
                    throw new \RuntimeException("Unable to activate the {$id} package.");
                }
                $destinationActivated = true;
                if (! $this->deletePath($staging)) {
                    throw new \RuntimeException("Unable to remove the staged {$id} package.");
                }
            } elseif ($this->renamePath($staging, $destination)) {
                $destinationActivated = true;
            } elseif (! $this->copyDirectoryExactly($staging, $destination)
                || ! $this->materializedManifestMatches($destination, $manifest)
            ) {
                throw new \RuntimeException("Unable to activate the {$id} package.");
            } else {
                $destinationActivated = true;
                if (! $this->deletePath($staging)) {
                    throw new \RuntimeException("Unable to remove the staged {$id} package.");
                }
            }
            $this->updateMaterializationJournal($journal, [
                'status' => 'materialized',
                'backup_mode' => $backupMode,
                'destination_backed_up' => $destinationBackedUp,
            ]);

            $this->materializationStates[$destination] = [
                'backup' => $destinationBackedUp ? $backup : null,
                'staging' => $staging,
                'mode' => $backupMode,
                'journal' => $journal,
            ];
        } catch (Throwable $exception) {
            $cleanupFailures = [];
            if (File::exists($staging) && ! $this->deletePath($staging)) {
                $cleanupFailures[] = $staging;
            }

            if ($destinationBackedUp && File::exists($backup)) {
                if ($backupMode === 'copy') {
                    if ($destinationActivated) {
                        if (! $this->copyDirectoryExactly($backup, $destination)) {
                            $cleanupFailures[] = $destination;
                        } elseif (File::exists($backup) && ! $this->deletePath($backup)) {
                            $cleanupFailures[] = $backup;
                        }
                    } elseif (! $this->copyDirectoryExactly($backup, $destination)
                        || ! $this->deletePath($backup)
                    ) {
                        $cleanupFailures[] = $backup;
                    }
                } else {
                    if ($this->pathExists($destination) && ! $this->deletePath($destination)) {
                        $cleanupFailures[] = $destination;
                    }
                    if (! $this->pathExists($destination) && ! $this->renamePath($backup, $destination)) {
                        $cleanupFailures[] = $backup;
                    }
                }
            } elseif ($destinationBackedUp) {
                $cleanupFailures[] = $backup;
            } elseif (! $hadDestination && File::exists($destination) && ! $this->deletePath($destination)) {
                $cleanupFailures[] = $destination;
            }

            if ($cleanupFailures !== []) {
                throw new \RuntimeException(
                    'Package materialization failed and rollback also failed: '.implode(', ', $cleanupFailures),
                    0,
                    $exception,
                );
            }

            throw $exception;
        }

        return $destination;
    }

    private function materializationJournalRoot(): string
    {
        $packagesRoot = storage_path('app/packages');
        $journalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.materialization-operations';
        File::ensureDirectoryExists($journalRoot);
        if (is_link($journalRoot)) {
            throw new \RuntimeException('Materialization journal root may not use symbolic links.');
        }

        return $journalRoot;
    }

    /**
     * @param  array<string, mixed>|null  $previousPackage
     * @param  array<string, mixed>|null  $previousLifecycle
     *
     * @phpstan-impure
     */
    private function prepareMaterializationJournal(
        PackageKind $kind,
        string $agovenaId,
        string $destination,
        string $staging,
        string $backup,
        bool $destinationExisted,
        ?string $previousFingerprint,
        ?array $previousPackage,
        ?array $previousLifecycle,
    ): string {
        $journalRoot = $this->materializationJournalRoot();
        $journal = $journalRoot.DIRECTORY_SEPARATOR.'materialize-'.bin2hex(random_bytes(12)).'.json';
        $this->assertManagedPath($destination, $this->installRoot($kind));
        $this->assertManagedPath($staging, $this->installRoot($kind));
        $this->assertManagedPath($backup, $this->installRoot($kind));
        $this->writeAtomicJson($journal, [
            'status' => 'prepared',
            'kind' => $kind->value,
            'agovena_id' => $agovenaId,
            'destination' => $destination,
            'staging' => $staging,
            'backup' => $backup,
            'destination_existed' => $destinationExisted,
            'destination_backed_up' => false,
            'backup_mode' => 'rename',
            'previous_fingerprint' => $previousFingerprint,
            'previous_state' => Crypt::encryptString(json_encode([
                'package' => $previousPackage,
                'lifecycle' => $previousLifecycle,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ], $journalRoot);

        return $journal;
    }

    /** @param array<string, mixed> $changes */
    private function updateMaterializationJournal(string $journal, array $changes): void
    {
        $this->withPackageLock('global:materialization-journal', function () use ($journal, $changes): void {
            $journalRoot = $this->materializationJournalRoot();
            $this->assertManagedPath($journal, $journalRoot);
            $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \RuntimeException('Materialization journal is invalid.');
            }
            foreach ($changes as $key => $value) {
                $payload[$key] = $value;
            }
            if (! in_array($payload['status'] ?? null, ['prepared', 'materialized', 'committed'], true)) {
                throw new \RuntimeException('Materialization journal has an invalid status.');
            }
            $this->writeAtomicJson($journal, $payload, $journalRoot);
        });
    }

    private function reconcileMaterializationJournals(): void
    {
        $this->withPackageLock('global:materialization-journal', function (): void {
            $journalRoot = $this->materializationJournalRoot();
            foreach (glob($journalRoot.DIRECTORY_SEPARATOR.'materialize-*.json') ?: [] as $journal) {
                $this->assertManagedPath($journal, $journalRoot);
                $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($payload)
                    || ! in_array($payload['status'] ?? null, ['prepared', 'materialized', 'committed'], true)
                    || ! is_string($payload['kind'] ?? null)
                    || ! is_string($payload['agovena_id'] ?? null)
                    || ! is_string($payload['destination'] ?? null)
                    || ! is_string($payload['staging'] ?? null)
                    || ! is_string($payload['backup'] ?? null)
                    || ! is_bool($payload['destination_existed'] ?? null)
                    || ! is_bool($payload['destination_backed_up'] ?? null)
                    || ! in_array($payload['backup_mode'] ?? null, ['rename', 'copy'], true)
                    || ! array_key_exists('previous_fingerprint', $payload)
                    || ! is_string($payload['previous_state'] ?? null)
                ) {
                    throw new \RuntimeException('Materialization journal payload is invalid.');
                }

                $kind = PackageKind::tryFrom($payload['kind']);
                if ($kind === null) {
                    throw new \RuntimeException('Materialization journal package kind is invalid.');
                }
                $destination = $payload['destination'];
                $staging = $payload['staging'];
                $backup = $payload['backup'];
                $this->assertManagedPath($destination, $this->installRoot($kind));
                $this->assertManagedPath($staging, $this->installRoot($kind));
                $this->assertManagedPath($backup, $this->installRoot($kind));
                if ($this->normalize($destination) !== $this->normalize($this->installRoot($kind).DIRECTORY_SEPARATOR.$payload['agovena_id'])) {
                    throw new \RuntimeException('Materialization journal destination is not canonical.');
                }

                if ($payload['status'] === 'committed') {
                    if (File::exists($staging) && ! $this->deletePath($staging)) {
                        throw new \RuntimeException('Committed materialization staging could not be removed.');
                    }
                    if (File::exists($backup) && ! $this->deletePath($backup)) {
                        throw new \RuntimeException('Committed materialization backup could not be removed.');
                    }
                    if (! $this->deletePath($journal)) {
                        throw new \RuntimeException('Committed materialization journal could not be removed.');
                    }

                    continue;
                }

                $previousState = json_decode(Crypt::decryptString($payload['previous_state']), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($previousState)
                    || (! array_key_exists('package', $previousState) || ($previousState['package'] !== null && ! is_array($previousState['package'])))
                    || (! array_key_exists('lifecycle', $previousState) || ($previousState['lifecycle'] !== null && ! is_array($previousState['lifecycle'])))
                ) {
                    throw new \RuntimeException('Materialization journal previous state is invalid.');
                }
                $previousFingerprint = $payload['previous_fingerprint'];
                if ($previousFingerprint !== null && ! is_string($previousFingerprint)) {
                    throw new \RuntimeException('Materialization journal fingerprint is invalid.');
                }

                if (File::exists($backup)) {
                    if (File::exists($destination) && ! $this->deletePath($destination)) {
                        throw new \RuntimeException('Interrupted materialization destination could not be cleared.');
                    }
                    if (! $this->copyDirectoryExactly($backup, $destination) || ! $this->deletePath($backup)) {
                        throw new \RuntimeException('Interrupted materialization backup could not be restored.');
                    }
                } elseif ($payload['destination_existed']) {
                    if ($this->packageTreeFingerprint($destination) !== $previousFingerprint) {
                        throw new \RuntimeException('Interrupted materialization has no recoverable backup.');
                    }
                } elseif (File::exists($destination) && ! $this->deletePath($destination)) {
                    throw new \RuntimeException('Interrupted materialization destination could not be removed.');
                }
                if (File::exists($staging) && ! $this->deletePath($staging)) {
                    throw new \RuntimeException('Interrupted materialization staging could not be removed.');
                }

                if ($payload['status'] === 'materialized') {
                    $this->restorePackage($kind, $payload['agovena_id'], $previousState['package']);
                    $this->restoreLifecycle($kind, $payload['agovena_id'], $previousState['lifecycle']);
                    $this->verifyRestoredState(
                        $kind,
                        $payload['agovena_id'],
                        $previousState['package'],
                        $previousState['lifecycle'],
                        $previousFingerprint,
                    );
                    $this->refreshManagers();
                    if ($this->lifecycleWasEnabled($previousState['lifecycle'])) {
                        $this->restoreEnabledRuntime($kind);
                    }
                }
                if (! $this->deletePath($journal)) {
                    throw new \RuntimeException('Recovered materialization journal could not be removed.');
                }
            }
        });
    }

    private function recoverInterruptedMaterialization(
        string $destination,
        string $backup,
        PackageKind $kind,
        string $id,
    ): void {
        $destinationValid = $this->materializedPackageIdentityMatches($destination, $kind, $id);
        $backupValid = $this->materializedPackageIdentityMatches($backup, $kind, $id);

        if ($destinationValid) {
            if (! $this->deletePath($backup)) {
                throw new \RuntimeException("Unable to remove the stale {$id} package backup.");
            }

            return;
        }

        if (! $backupValid) {
            throw new \RuntimeException("Unable to recover the unfinished {$id} package update.");
        }
        if (File::exists($destination) && ! $this->deletePath($destination)) {
            throw new \RuntimeException("Unable to remove the invalid {$id} package destination.");
        }
        if (! $this->renamePath($backup, $destination)) {
            throw new \RuntimeException("Unable to restore the unfinished {$id} package update.");
        }
    }

    private function materializedPackageIdentityMatches(string $directory, PackageKind $kind, string $id): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        try {
            $manifest = $this->manifests->read($directory);
        } catch (Throwable) {
            return false;
        }

        return $manifest['kind'] === $kind && $manifest['id'] === $id;
    }

    /** @param array<string, mixed> $manifest */
    /** @phpstan-impure */
    private function materializedManifestMatches(string $directory, array $manifest): bool
    {
        try {
            $actual = $this->manifests->read($directory);
        } catch (Throwable) {
            return false;
        }

        return $actual['id'] === $manifest['id']
            && $actual['kind'] === $manifest['kind']
            && $actual['version'] === $manifest['version'];
    }

    private function rollbackMaterialization(?string $destination): void
    {
        if ($destination === null) {
            return;
        }

        $state = $this->materializationStates[$destination] ?? null;
        if ($state === null) {
            return;
        }

        if (File::exists($state['staging']) && ! $this->deletePath($state['staging'])) {
            throw new \RuntimeException('Unable to remove the staged package during rollback.');
        }

        if ($state['backup'] !== null && File::exists($state['backup'])) {
            if ($state['mode'] === 'copy') {
                if (! $this->copyDirectoryExactly($state['backup'], $destination)
                    || ! $this->deletePath($state['backup'])
                ) {
                    throw new \RuntimeException('Unable to restore the previous package during rollback.');
                }
            } else {
                if (File::exists($destination) && ! $this->deletePath($destination)) {
                    throw new \RuntimeException('Unable to remove the failed package during rollback.');
                }
                if (! $this->renamePath($state['backup'], $destination)) {
                    throw new \RuntimeException('Unable to restore the previous package during rollback.');
                }
            }
        } elseif (File::exists($destination) && ! $this->deletePath($destination)) {
            throw new \RuntimeException('Unable to remove the failed package during rollback.');
        }

        if (File::exists($state['journal']) && ! $this->deletePath($state['journal'])) {
            throw new \RuntimeException('Unable to remove the package materialization journal during rollback.');
        }

        unset($this->materializationStates[$destination]);
    }

    /** @phpstan-impure */
    private function renamePath(string $source, string $destination): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            clearstatcache(true, $source);
            clearstatcache(true, $destination);
            if (@rename($source, $destination)) {
                return true;
            }
            usleep(100_000);
        }

        return false;
    }

    private function assertExistingInstallPath(?string $installPath, string $canonicalDestination): void
    {
        if ($installPath === null || trim($installPath) === '') {
            return;
        }

        $root = dirname($canonicalDestination);
        $this->assertManagedPath($installPath, $root);
        if ($this->normalize($installPath) !== $this->normalize($canonicalDestination)) {
            throw new \RuntimeException('Existing package install path is not canonical.');
        }

        $resolved = realpath($installPath);
        if ($resolved !== false && $this->normalize($resolved) !== $this->normalize($canonicalDestination)) {
            throw new \RuntimeException('Existing package install path resolves to a different destination.');
        }
    }

    private function assertManagedPath(string $path, string $root): void
    {
        if (is_link($root) || is_link($path)) {
            throw new \RuntimeException('Package paths may not use symbolic links.');
        }

        $rootResolved = realpath($root);
        $rootNormalized = $this->normalize($rootResolved ?: $root);
        $pathNormalized = $this->normalize($path);
        if ($pathNormalized === $rootNormalized || ! str_starts_with($pathNormalized, $rootNormalized.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Package path is outside the managed package root.');
        }

        $resolved = realpath($path);
        if ($resolved !== false) {
            $resolved = $this->normalize($resolved);
            if ($resolved === $rootNormalized || ! str_starts_with($resolved, $rootNormalized.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Package path resolves outside the managed package root.');
            }

            return;
        }

        $parent = realpath(dirname($path));
        if ($parent === false) {
            throw new \RuntimeException('Package path parent is unavailable.');
        }
        $parent = $this->normalize($parent);
        if ($parent !== $rootNormalized && ! str_starts_with($parent, $rootNormalized.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Package path parent resolves outside the managed package root.');
        }
    }

    private function assertNoSymlinks(string $path): void
    {
        if (is_link($path)) {
            throw new \RuntimeException('Package trees may not contain symbolic links.');
        }
        if (! is_dir($path)) {
            return;
        }

        foreach (new \DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                throw new \RuntimeException('Package trees may not contain symbolic links.');
            }
            if ($entry->isDir()) {
                $this->assertNoSymlinks($entry->getPathname());
            }
        }
    }

    /** @phpstan-impure */
    private function pathExists(string $path): bool
    {
        clearstatcache(true, $path);

        return File::exists($path);
    }

    /** @phpstan-impure */
    private function deletePath(string $path): bool
    {
        if (is_link($path)) {
            return @unlink($path) || ! is_link($path);
        }
        if (! file_exists($path)) {
            return true;
        }

        if (is_dir($path)) {
            try {
                foreach (new \DirectoryIterator($path) as $entry) {
                    if ($entry->isDot()) {
                        continue;
                    }
                    if (! $this->deletePath($entry->getPathname())) {
                        return false;
                    }
                }
            } catch (Throwable) {
                return false;
            }

            $removed = @rmdir($path);
            clearstatcache(true, $path);

            return $removed || ! file_exists($path);
        }

        $removed = @unlink($path);
        clearstatcache(true, $path);

        return $removed || ! file_exists($path);
    }

    private function deletePathWithRetry(string $path): bool
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            if ($this->deletePath($path)) {
                return true;
            }
            usleep(100_000);
        }

        return false;
    }

    /**
     * Copy a package while removing files that no longer exist in the source.
     *
     * @phpstan-impure
     */
    private function copyDirectoryExactly(string $source, string $destination): bool
    {
        if (! is_dir($source)) {
            return false;
        }

        $sourceFiles = [];
        foreach (File::allFiles($source) as $file) {
            $relative = $this->relativePath($source, $file->getPathname());
            $sourceFiles[$relative] = $file->getPathname();
        }

        if (File::exists($destination)) {
            foreach (File::allFiles($destination) as $file) {
                $relative = $this->relativePath($destination, $file->getPathname());
                if (! array_key_exists($relative, $sourceFiles) && ! $this->deletePath($file->getPathname())) {
                    return false;
                }
            }

            $directories = File::allDirectories($destination);
            usort($directories, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
            foreach ($directories as $directory) {
                $relative = $this->relativePath($destination, $directory);
                $sourceDirectory = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (! is_dir($sourceDirectory) && ! $this->deletePath($directory)) {
                    return false;
                }
            }
        }

        if (! File::copyDirectory($source, $destination)) {
            return false;
        }

        foreach ($sourceFiles as $relative => $sourceFile) {
            $destinationFile = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! File::exists($destinationFile)
                || hash_file('sha256', $sourceFile) === false
                || hash_file('sha256', $destinationFile) !== hash_file('sha256', $sourceFile)
            ) {
                return false;
            }
        }

        foreach (File::allFiles($destination) as $file) {
            if (! array_key_exists($this->relativePath($destination, $file->getPathname()), $sourceFiles)) {
                return false;
            }
        }

        return true;
    }

    private function relativePath(string $root, string $path): string
    {
        $root = $this->normalize($root);
        $path = $this->normalize($path);

        return trim(str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root))), '/');
    }

    /** @param array<string, mixed>|null $previousPackage @param array<string, mixed>|null $previousLifecycle */
    private function rollbackFailedInstall(
        ?string $destination,
        PackageKind $kind,
        string $agovenaId,
        ?array $previousPackage,
        ?array $previousLifecycle,
        ?string $previousFingerprint,
        Throwable $primary,
    ): ?Throwable {
        $failures = [];

        try {
            if ($kind === PackageKind::Module) {
                $this->modules->uninstall($agovenaId, purgeData: false);
            } else {
                $this->extensions->uninstall($agovenaId, purgeData: false);
            }
        } catch (Throwable $exception) {
            $failures[] = 'lifecycle cleanup: '.$exception->getMessage();
        }

        try {
            $this->rollbackMaterialization($destination);
        } catch (Throwable $exception) {
            $failures[] = 'filesystem rollback: '.$exception->getMessage();
        }

        try {
            $this->restorePackage($kind, $agovenaId, $previousPackage);
            $this->restoreLifecycle($kind, $agovenaId, $previousLifecycle);
        } catch (Throwable $exception) {
            $failures[] = 'database rollback: '.$exception->getMessage();
        }

        try {
            $this->verifyRestoredState(
                $kind,
                $agovenaId,
                $previousPackage,
                $previousLifecycle,
                $previousFingerprint,
            );
        } catch (Throwable $exception) {
            $failures[] = 'state verification: '.$exception->getMessage();
        }

        try {
            $this->refreshManagers();
        } catch (Throwable $exception) {
            $failures[] = 'runtime refresh: '.$exception->getMessage();
        }

        if ($failures === [] && $this->lifecycleWasEnabled($previousLifecycle)) {
            try {
                $this->restoreEnabledRuntime($kind);
            } catch (Throwable $exception) {
                $failures[] = 'runtime restore: '.$exception->getMessage();
            }
        }

        if ($failures !== []) {
            $failurePhases = array_map(
                static fn (string $failure): string => strstr($failure, ':', true) ?: $failure,
                $failures,
            );

            return new \RuntimeException(
                'Package rollback did not complete for '.$kind->value
                    .' (failed phases: '.implode(', ', $failurePhases).').',
                0,
                $primary,
            );
        }

        return null;
    }

    private function verifyRestoredState(
        PackageKind $kind,
        string $agovenaId,
        ?array $previousPackage,
        ?array $previousLifecycle,
        ?string $previousFingerprint,
    ): void {
        $actualPackage = DB::table('agovena_packages')
            ->where('kind', $kind->value)
            ->where('agovena_id', $agovenaId)
            ->first();
        if (($previousPackage === null) !== ($actualPackage === null)
            || ($previousPackage !== null
                && ! $this->rowsMatch($previousPackage, (array) $actualPackage))
        ) {
            throw new \RuntimeException('Package rollback verification failed for package state.');
        }

        $table = $kind === PackageKind::Module ? 'agovena_modules' : 'agovena_extensions';
        $column = $kind === PackageKind::Module ? 'module_id' : 'extension_id';
        $actualLifecycle = DB::table($table)->where($column, $agovenaId)->first();
        if (($previousLifecycle === null) !== ($actualLifecycle === null)
            || ($previousLifecycle !== null
                && ! $this->rowsMatch($previousLifecycle, (array) $actualLifecycle))
        ) {
            throw new \RuntimeException('Package rollback verification failed for lifecycle state.');
        }

        $destination = $this->installRoot($kind).DIRECTORY_SEPARATOR.$agovenaId;
        if ($this->packageTreeFingerprint($destination) !== $previousFingerprint) {
            throw new \RuntimeException('Package rollback verification failed for filesystem state.');
        }
    }

    /** @param array<string, mixed> $expected @param array<string, mixed> $actual */
    private function rowsMatch(array $expected, array $actual): bool
    {
        return $this->normalizeRow($expected) === $this->normalizeRow($actual);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            }
            $normalized[(string) $key] = $value;
        }
        ksort($normalized);

        return $normalized;
    }

    private function packageTreeFingerprint(string $path): ?string
    {
        if (is_link($path) || ! file_exists($path)) {
            return null;
        }
        if (! is_dir($path)) {
            $hash = hash_file('sha256', $path);

            return $hash === false ? null : 'file:'.$hash;
        }

        $this->assertNoSymlinks($path);
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $relative = $this->relativePath($path, $entry->getPathname());
            if ($entry->isDir()) {
                $entries[] = 'D|'.$relative;

                continue;
            }

            $hash = hash_file('sha256', $entry->getPathname());
            if ($hash === false) {
                throw new \RuntimeException('Unable to fingerprint the package files.');
            }
            $entries[] = 'F|'.$relative.'|'.$hash;
        }
        sort($entries);

        return 'directory:'.hash('sha256', implode("\n", $entries));
    }

    private function lifecycleWasEnabled(?array $previous): bool
    {
        return in_array($previous['enabled'] ?? false, [true, 1, '1'], true);
    }

    /** @param array<string, mixed>|null $previous */
    private function restoreEnabledState(PackageKind $kind, string $agovenaId, ?array $previous): void
    {
        if (! $this->lifecycleWasEnabled($previous)) {
            return;
        }

        if ($kind === PackageKind::Module) {
            $this->modules->enable($agovenaId);
        } else {
            $this->extensions->enable($agovenaId);
        }
    }

    private function restoreEnabledRuntime(PackageKind $kind): void
    {
        if ($kind === PackageKind::Module) {
            $this->modules->bootEnabled();
        } else {
            $this->extensions->rebuildRuntime();
        }
    }

    /** @return array<string, mixed>|null */
    private function lifecycleAttributes(PackageKind $kind, string $agovenaId): ?array
    {
        $table = $kind === PackageKind::Module ? 'agovena_modules' : 'agovena_extensions';
        $column = $kind === PackageKind::Module ? 'module_id' : 'extension_id';
        $row = DB::table($table)->where($column, $agovenaId)->first();

        return $row === null ? null : (array) $row;
    }

    /** @param array<string, mixed>|null $previous */
    private function restorePackage(PackageKind $kind, string $agovenaId, ?array $previous): void
    {
        $query = DB::table('agovena_packages')
            ->where('kind', $kind->value)
            ->where('agovena_id', $agovenaId);
        if ($previous === null) {
            $query->delete();

            return;
        }

        $identity = isset($previous['id']) ? ['id' => $previous['id']] : [
            'kind' => $kind->value,
            'agovena_id' => $agovenaId,
        ];
        $values = $previous;
        if (isset($values['id'])) {
            unset($values['id']);
        }
        DB::table('agovena_packages')->updateOrInsert($identity, $values);
    }

    /** @param array<string, mixed>|null $previous */
    private function restoreLifecycle(PackageKind $kind, string $agovenaId, ?array $previous): void
    {
        $table = $kind === PackageKind::Module ? 'agovena_modules' : 'agovena_extensions';
        $column = $kind === PackageKind::Module ? 'module_id' : 'extension_id';
        $query = DB::table($table)->where($column, $agovenaId);
        if ($previous === null) {
            $query->delete();

            return;
        }

        $identity = isset($previous['id']) ? ['id' => $previous['id']] : [$column => $agovenaId];
        $values = $previous;
        if (isset($values['id'])) {
            unset($values['id']);
        }
        DB::table($table)->updateOrInsert($identity, $values);
    }

    /** @return array{destination: string, snapshot: string}|null */
    private function planPackageTreeSnapshot(AgovenaPackage $package): ?array
    {
        $destination = $package->install_path;
        if (! is_string($destination) || $destination === '' || ! File::exists($destination)) {
            return null;
        }
        if (! is_dir($destination) || is_link($destination)) {
            throw new \RuntimeException('Package install path is not a safe directory.');
        }

        $root = $this->normalize(realpath($this->installRoot($package->kind)) ?: $this->installRoot($package->kind));
        $resolved = realpath($destination);
        if ($resolved === false) {
            throw new \RuntimeException('Package install path is unavailable.');
        }
        $resolved = $this->normalize($resolved);
        $canonical = $this->normalize($root.DIRECTORY_SEPARATOR.$package->agovena_id);
        $this->assertManagedPath($canonical, $root);
        if ($resolved !== $canonical) {
            throw new \RuntimeException('Package install path does not match its canonical package directory.');
        }
        $this->assertNoSymlinks($resolved);

        $snapshot = storage_path('app/packages/.purge.'.bin2hex(random_bytes(12)).'.snapshot');
        $this->assertManagedPath($snapshot, storage_path('app/packages'));

        return ['destination' => $resolved, 'snapshot' => $snapshot];
    }

    /** @param array{destination: string, snapshot: string} $state */
    private function restorePackageTree(array $state): void
    {
        if (File::exists($state['destination']) && ! $this->deletePath($state['destination'])) {
            throw new \RuntimeException('Unable to clear the failed package files.');
        }
        if (! $this->copyDirectoryExactly($state['snapshot'], $state['destination'])) {
            throw new \RuntimeException('Unable to restore the package files.');
        }
    }

    private function snapshotComposerStateForPackage(AgovenaPackage $package): ?array
    {
        if (! in_array($package->source_type, [PackageSourceType::Composer, PackageSourceType::Vcs], true)) {
            return null;
        }

        return $this->snapshotComposerState(new PackageSource(
            kind: $package->kind,
            sourceType: $package->source_type,
            locator: (string) $package->source_locator,
            constraint: $package->version_constraint,
            composerName: $package->composer_name,
        ));
    }

    private function purgeFiles(AgovenaPackage $package): void
    {
        if ($package->source_type !== PackageSourceType::Monorepo
            && $package->source_type !== PackageSourceType::Zip
            && $package->source_type !== PackageSourceType::Path
            && is_string($package->composer_name)
            && $package->composer_name !== ''
        ) {
            $this->composer->remove($package->composer_name);
        }

        $path = $package->install_path;
        if (! is_string($path) || $path === '') {
            return;
        }

        if (is_link($path)) {
            throw new \RuntimeException('Package install paths may not use symbolic links.');
        }

        $rootPath = $this->installRoot($package->kind);
        $root = $this->normalize(realpath($rootPath) ?: $rootPath);
        $canonical = $this->normalize($root.DIRECTORY_SEPARATOR.$package->agovena_id);
        $this->assertManagedPath($canonical, $root);
        $resolved = realpath($path);
        if ($resolved === false) {
            return;
        }

        $normalized = $this->normalize($resolved);
        if ($normalized !== $canonical) {
            throw new \RuntimeException('Package install path does not match its canonical package directory.');
        }

        if (! $this->deletePath($resolved)) {
            throw new \RuntimeException('Unable to purge the package files.');
        }
    }

    private function installRoot(PackageKind $kind): string
    {
        return $kind === PackageKind::Module
            ? storage_path('app/packages/modules')
            : storage_path('app/packages/extensions');
    }

    private function refreshManagers(): void
    {
        $this->modules->refresh();
        $this->extensions->refresh();
    }

    /**
     * @param  array{destination: string, snapshot: string}|null  $treeSnapshot
     * @param  array<string, mixed>|null  $previousPackage
     * @param  array<string, mixed>|null  $previousLifecycle
     */
    private function preparePurgeJournal(
        ?array $treeSnapshot,
        PackageKind $kind,
        string $agovenaId,
        ?array $previousPackage,
        ?array $previousLifecycle,
        ?string $previousFingerprint,
    ): string {
        return $this->withPackageLock('global:purge-journal', function () use ($treeSnapshot, $kind, $agovenaId, $previousPackage, $previousLifecycle, $previousFingerprint): string {
            $packagesRoot = storage_path('app/packages');
            $journalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.purge-operations';
            File::ensureDirectoryExists($journalRoot);
            if (is_link($journalRoot)) {
                throw new \RuntimeException('Purge journal root may not use symbolic links.');
            }
            $journal = $journalRoot.DIRECTORY_SEPARATOR.'purge-'.bin2hex(random_bytes(12)).'.json';
            $this->writeAtomicJson($journal, [
                'status' => 'prepared',
                'kind' => $kind->value,
                'agovena_id' => $agovenaId,
                'destination' => $treeSnapshot['destination'] ?? null,
                'snapshot' => $treeSnapshot['snapshot'] ?? null,
                'snapshot_ready' => false,
                'previous_state' => Crypt::encryptString(json_encode([
                    'package' => $previousPackage,
                    'lifecycle' => $previousLifecycle,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'previous_fingerprint' => $previousFingerprint,
            ], $journalRoot);

            return $journal;
        });
    }

    private function setPurgeJournalStatus(string $journal, string $status): void
    {
        $this->withPackageLock('global:purge-journal', function () use ($journal, $status): void {
            $packagesRoot = storage_path('app/packages');
            $this->assertManagedPath($journal, $packagesRoot.DIRECTORY_SEPARATOR.'.purge-operations');
            $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)
                || ! array_key_exists('snapshot', $payload)
                || ! array_key_exists('destination', $payload)
                || ! in_array($status, ['prepared', 'committed'], true)
            ) {
                throw new \RuntimeException('Purge journal is invalid.');
            }
            $payload['status'] = $status;
            $this->writeAtomicJson($journal, $payload, $packagesRoot.DIRECTORY_SEPARATOR.'.purge-operations');
        });
    }

    private function setPurgeJournalSnapshotReady(string $journal): void
    {
        $this->withPackageLock('global:purge-journal', function () use ($journal): void {
            $packagesRoot = storage_path('app/packages');
            $journalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.purge-operations';
            $this->assertManagedPath($journal, $journalRoot);
            $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)
                || ! array_key_exists('snapshot', $payload)
                || ! array_key_exists('destination', $payload)
                || ($payload['snapshot_ready'] ?? null) !== false
            ) {
                throw new \RuntimeException('Purge journal snapshot state is invalid.');
            }
            $payload['snapshot_ready'] = true;
            $this->writeAtomicJson($journal, $payload, $journalRoot);
        });
    }

    private function reconcilePurgeJournals(): void
    {
        $this->withPackageLock('global:purge-journal', function (): void {
            $packagesRoot = storage_path('app/packages');
            $journalRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.purge-operations';
            File::ensureDirectoryExists($journalRoot);
            if (is_link($journalRoot)) {
                throw new \RuntimeException('Purge journal root may not use symbolic links.');
            }
            $trackedSnapshots = [];
            foreach (glob($journalRoot.DIRECTORY_SEPARATOR.'purge-*.json') ?: [] as $journal) {
                $this->assertManagedPath($journal, $journalRoot);
                $payload = json_decode((string) File::get($journal), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \RuntimeException('Purge journal is invalid.');
                }
                $status = $payload['status'] ?? null;
                if (! in_array($status, ['prepared', 'committed'], true)) {
                    throw new \RuntimeException('Purge journal has an invalid status.');
                }

                $kind = PackageKind::tryFrom((string) ($payload['kind'] ?? ''));
                $agovenaId = $payload['agovena_id'] ?? null;
                $destination = $payload['destination'] ?? null;
                $snapshot = $payload['snapshot'] ?? null;
                $snapshotReady = $payload['snapshot_ready'] ?? true;
                $previousState = $payload['previous_state'] ?? null;
                $previousFingerprint = $payload['previous_fingerprint'] ?? null;
                if ($kind === null || ! is_string($agovenaId) || $agovenaId === ''
                    || ($destination !== null && ! is_string($destination))
                    || ($snapshot !== null && ! is_string($snapshot))
                    || ! is_bool($snapshotReady)
                    || ! is_string($previousState)
                    || ($previousFingerprint !== null && ! is_string($previousFingerprint))
                ) {
                    throw new \RuntimeException('Purge journal payload is invalid.');
                }
                $previousState = $this->decryptPackageOperationState($previousState);
                $previousPackage = $previousState['package'];
                $previousLifecycle = $previousState['lifecycle'];
                if ($snapshot !== null) {
                    $this->assertManagedPath($snapshot, $packagesRoot);
                    $trackedSnapshots[] = $this->normalize($snapshot);
                }
                if ($destination !== null) {
                    $canonical = $this->normalize($this->installRoot($kind).DIRECTORY_SEPARATOR.$agovenaId);
                    $this->assertManagedPath($canonical, $this->installRoot($kind));
                    if ($this->normalize($destination) !== $canonical) {
                        throw new \RuntimeException('Purge journal destination is not canonical.');
                    }
                }

                if ($status === 'committed') {
                    if ($snapshot !== null && File::exists($snapshot) && ! $this->deletePathWithRetry($snapshot)) {
                        report(new \RuntimeException('Committed purge snapshot cleanup was deferred.'));

                        continue;
                    }
                    if (! $this->deletePathWithRetry($journal)) {
                        report(new \RuntimeException('Committed purge journal cleanup was deferred.'));
                    }

                    continue;
                }

                $treeSnapshot = $destination !== null && $snapshot !== null
                    ? ['destination' => $destination, 'snapshot' => $snapshot]
                    : null;
                if ($snapshotReady && $treeSnapshot !== null && File::exists($snapshot)) {
                    $this->restorePackageTree($treeSnapshot);
                }
                $this->restorePackage($kind, $agovenaId, $previousPackage);
                $this->restoreLifecycle($kind, $agovenaId, $previousLifecycle);
                $this->verifyRestoredState($kind, $agovenaId, $previousPackage, $previousLifecycle, $previousFingerprint);
                $this->refreshManagers();
                if ($this->lifecycleWasEnabled($previousLifecycle)) {
                    $this->restoreEnabledRuntime($kind);
                }
                if ($snapshot !== null && File::exists($snapshot) && ! $this->deletePath($snapshot)) {
                    throw new \RuntimeException('Recovered purge snapshot could not be removed.');
                }
                if (! $this->deletePath($journal)) {
                    throw new \RuntimeException('Recovered purge journal could not be removed.');
                }
            }

            foreach (glob($packagesRoot.DIRECTORY_SEPARATOR.'.purge.*.snapshot') ?: [] as $snapshot) {
                $this->assertManagedPath($snapshot, $packagesRoot);
                if (! in_array($this->normalize($snapshot), $trackedSnapshots, true)
                    && ! $this->deletePath($snapshot)
                ) {
                    throw new \RuntimeException('Orphaned purge snapshot could not be removed.');
                }
            }
        });
    }

    private function writeAtomicJson(string $path, array $payload, string $allowedRoot): void
    {
        $this->assertManagedPath($path, $allowedRoot);
        $temporary = $path.'.tmp.'.bin2hex(random_bytes(12));
        $this->assertManagedPath($temporary, $allowedRoot);
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        $handle = @fopen($temporary, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('JSON journal could not be opened.');
        }
        try {
            $offset = 0;
            $length = strlen($json);
            while ($offset < $length) {
                $written = fwrite($handle, substr($json, $offset));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('JSON journal could not be written.');
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new \RuntimeException('JSON journal could not be flushed.');
            }
            if (function_exists('fsync') && ! fsync($handle)) {
                throw new \RuntimeException('JSON journal could not be synced.');
            }
        } finally {
            fclose($handle);
        }

        try {
            for ($attempt = 0; $attempt < 4; $attempt++) {
                if (@rename($temporary, $path)) {
                    return;
                }
                usleep(100_000);
            }
            throw new \RuntimeException('JSON journal could not be atomically replaced.');
        } finally {
            if (File::exists($temporary)) {
                $this->deletePath($temporary);
            }
        }
    }

    private function cleanupPendingPackageArtifacts(): void
    {
        $this->withPackageLock('global:package-cleanup-journal', function (): void {
            $this->cleanupPendingPackageArtifactsUnlocked();
        });
    }

    private function cleanupPendingPackageArtifactsUnlocked(): void
    {
        $packagesRoot = storage_path('app/packages');
        $journal = $packagesRoot.DIRECTORY_SEPARATOR.'.package-cleanup.json';
        $this->assertManagedPath($journal, $packagesRoot);
        if (! File::exists($journal)) {
            return;
        }

        $uploadsRoot = $packagesRoot.DIRECTORY_SEPARATOR.'uploads';
        File::ensureDirectoryExists($uploadsRoot);
        $entries = json_decode((string) File::get($journal), true);
        if (! is_array($entries)) {
            throw new \RuntimeException('Package cleanup journal is invalid.');
        }

        $remaining = [];
        foreach ($entries as $path) {
            if (! is_string($path)) {
                throw new \RuntimeException('Package cleanup journal contains an invalid path.');
            }
            $this->assertManagedPath($path, $uploadsRoot);
            if (File::exists($path) && ! $this->deletePath($path)) {
                $remaining[] = $path;
            }
        }

        if ($remaining === []) {
            $this->deletePath($journal);

            return;
        }

        $this->writeCleanupJournal($journal, array_values(array_unique($remaining)));
        throw new \RuntimeException('Pending package cleanup could not be completed.');
    }

    private function queuePendingPackageCleanup(string $path): void
    {
        $this->withPackageLock('global:package-cleanup-journal', function () use ($path): void {
            $this->queuePendingPackageCleanupUnlocked($path);
        });
    }

    private function queuePendingPackageCleanupUnlocked(string $path): void
    {
        $packagesRoot = storage_path('app/packages');
        $uploadsRoot = $packagesRoot.DIRECTORY_SEPARATOR.'uploads';
        $journal = $packagesRoot.DIRECTORY_SEPARATOR.'.package-cleanup.json';
        File::ensureDirectoryExists($uploadsRoot);
        $this->assertManagedPath($journal, $packagesRoot);
        $this->assertManagedPath($path, $uploadsRoot);
        $entries = File::exists($journal) ? json_decode((string) File::get($journal), true) : [];
        if (! is_array($entries)) {
            $entries = [];
        }
        $entries[] = $path;
        $this->writeCleanupJournal($journal, array_values(array_unique($entries)));
    }

    private function writeCleanupJournal(string $journal, array $entries): void
    {
        $this->writeAtomicJson($journal, $entries, storage_path('app/packages'));
    }

    private function withPackageLock(string $key, Closure $callback): mixed
    {
        $packagesRoot = storage_path('app/packages');
        $lockRoot = $packagesRoot.DIRECTORY_SEPARATOR.'.locks';
        File::ensureDirectoryExists($packagesRoot);
        if (is_link($packagesRoot)) {
            throw new \RuntimeException('Package root may not use symbolic links.');
        }
        File::ensureDirectoryExists($lockRoot);
        if (is_link($lockRoot)) {
            throw new \RuntimeException('Package lock root may not use symbolic links.');
        }

        $lockPath = $lockRoot.DIRECTORY_SEPARATOR.hash('sha256', $key).'.lock';
        $this->assertManagedPath($lockPath, $packagesRoot);
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false || ! @flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException('Unable to lock the package operation.');
        }

        try {
            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function requirePackage(PackageKind $kind, string $agovenaId): AgovenaPackage
    {
        $package = AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $agovenaId)
            ->first();

        if ($package === null) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.not_found'),
            ]);
        }

        return $package;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function resolveMonorepoOrigin(PackageSource $source): string
    {
        $packageKey = (string) $source->composerName;
        $mapping = $this->monorepoMap->resolve($packageKey, $source->kind);
        $subdirectory = $source->subdirectory ?? $mapping['path'];

        if ($source->subdirectory !== null) {
            $this->monorepoMap->assertSubdirectory($source->subdirectory);
            if ($source->subdirectory !== $mapping['path']) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.monorepo_subdirectory_mismatch', [
                        'expected' => $mapping['path'],
                        'actual' => $source->subdirectory,
                    ]),
                ]);
            }
        }

        $repository = trim($source->locator);
        if ($repository === '') {
            $repository = $this->monorepoMap->defaultRepository();
        }

        $ref = $source->constraint;
        if ($ref === '' || $ref === '*') {
            $ref = 'main';
        }

        $localRoot = OptionalPackagesPath::root();
        if ($localRoot !== null) {
            $candidate = $localRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
            $resolved = realpath($candidate);
            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        return $this->monorepoCheckout->resolve($repository, $ref, $subdirectory);
    }

    private function resolvedSourceLocator(PackageSource $source): string
    {
        if ($source->sourceType !== PackageSourceType::Monorepo) {
            return $source->locator;
        }

        $repository = trim($source->locator);

        return $repository !== '' ? $repository : $this->monorepoMap->defaultRepository();
    }
}

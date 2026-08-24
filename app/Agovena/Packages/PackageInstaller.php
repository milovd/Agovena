<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Composer\Semver\Comparator;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

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
    ) {}

    public function install(PackageSource $source): AgovenaPackage
    {
        $this->validator->assert($source);

        $origin = $this->resolveOrigin($source);
        $manifest = $this->manifests->read($origin);
        $this->validator->assertKind($source->kind, $manifest['kind']);
        $this->validator->assertAgovenaId($manifest['id']);

        $destination = $this->materialize($origin, $manifest['kind'], $manifest['id']);
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

        try {
            if ($manifest['kind'] === PackageKind::Module) {
                $this->modules->install($manifest['id']);
            } else {
                $this->extensions->install($manifest['id']);
            }
        } catch (\Throwable $exception) {
            $this->rollbackFailedInstall($package, $destination, $manifest['kind'], $manifest['id']);
            throw $exception;
        }

        return $package->fresh() ?? $package;
    }

    public function update(PackageKind $kind, string $agovenaId): AgovenaPackage
    {
        $package = $this->requirePackage($kind, $agovenaId);
        if ($package->is_bundled || $package->source_type === PackageSourceType::Bundled) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.cannot_update_bundled'),
            ]);
        }

        $source = new PackageSource(
            kind: $package->kind,
            sourceType: $package->source_type,
            locator: (string) $package->source_locator,
            constraint: $package->version_constraint,
            composerName: $package->composer_name,
        );

        $updated = $this->install($source);

        return $updated;
    }

    /**
     * Remove from runtime. Data/settings are preserved. Remote files stay on disk unless $purgeFiles.
     */
    public function uninstall(PackageKind $kind, string $agovenaId, bool $purgeFiles = false): void
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
        $package = AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $agovenaId)
            ->first();

        if ($package === null || $package->is_bundled) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.cannot_purge_bundled'),
            ]);
        }

        $this->uninstall($kind, $agovenaId, purgeFiles: true);
    }

    public function hasUpdate(AgovenaPackage $package): bool
    {
        if ($package->available_version === null || $package->installed_version === null) {
            return false;
        }

        try {
            return Comparator::greaterThan($package->available_version, $package->installed_version);
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveOrigin(PackageSource $source): string
    {
        return match ($source->sourceType) {
            PackageSourceType::Path => $this->validator->assertPath($source->locator),
            PackageSourceType::Zip => $this->zipExtractor->extract($source->locator, $source->kind),
            PackageSourceType::Composer => $this->composer->require(
                $source->composerName ?? $source->locator,
                $source->constraint,
            )->path,
            PackageSourceType::Vcs => $this->composer->require(
                (string) $source->composerName,
                $source->constraint,
                $source->locator,
            )->path,
            PackageSourceType::Monorepo => $this->resolveMonorepoOrigin($source),
            PackageSourceType::Bundled => throw ValidationException::withMessages([
                'package' => __('admin.packages.bundled_use_lifecycle'),
            ]),
        };
    }

    private function materialize(string $origin, PackageKind $kind, string $id): string
    {
        $destination = $this->installRoot($kind).DIRECTORY_SEPARATOR.$id;
        File::ensureDirectoryExists($this->installRoot($kind));

        $originNormalized = $this->normalize($origin);
        $destinationNormalized = $this->normalize($destination);

        if ($originNormalized !== $destinationNormalized) {
            if (is_dir($destination)) {
                File::deleteDirectory($destination);
            }
            File::copyDirectory($origin, $destination);
        }

        return $destination;
    }

    private function rollbackFailedInstall(
        AgovenaPackage $package,
        string $destination,
        PackageKind $kind,
        string $agovenaId,
    ): void {
        try {
            if ($kind === PackageKind::Module) {
                $this->modules->uninstall($agovenaId, purgeData: false);
            } else {
                $this->extensions->uninstall($agovenaId, purgeData: false);
            }
        } catch (\Throwable) {
            // Best-effort cleanup after a failed install.
        }

        $package->delete();

        $root = $this->normalize($this->installRoot($kind));
        $resolved = realpath($destination);
        if ($resolved !== false) {
            $normalized = $this->normalize($resolved);
            if ($normalized !== $root && str_starts_with($normalized, $root.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }

        $this->refreshManagers();
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

        $root = $this->normalize($this->installRoot($package->kind));
        $resolved = realpath($path);
        if ($resolved === false) {
            return;
        }

        $normalized = $this->normalize($resolved);
        if ($normalized === $root || ! str_starts_with($normalized, $root.DIRECTORY_SEPARATOR)) {
            return;
        }

        File::deleteDirectory($resolved);
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

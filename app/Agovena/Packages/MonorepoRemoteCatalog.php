<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Extensions\ExtensionManifest;
use App\Agovena\Modules\ModuleManifest;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Illuminate\Support\Facades\File;

final class MonorepoRemoteCatalog
{
    public function __construct(
        private readonly MonorepoPackageMap $map,
        private readonly MonorepoCheckout $checkout,
        private readonly PackageManifestReader $manifests,
    ) {}

    /**
     * @return list<array{key: string, kind: PackageKind, path: string}>
     */
    public function entries(?PackageKind $kind = null): array
    {
        $packages = config('agovena.packages.monorepo.packages', []);
        if (! is_array($packages)) {
            return [];
        }

        $rows = [];
        foreach ($packages as $key => $entry) {
            if (! is_string($key) || ! is_array($entry)) {
                continue;
            }

            try {
                $this->map->assertPackageKey($key);
                $resolved = $this->map->resolve($key);
            } catch (\Throwable) {
                continue;
            }

            if ($kind !== null && $resolved['kind'] !== $kind) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'kind' => $resolved['kind'],
                'path' => $resolved['path'],
            ];
        }

        return $rows;
    }

    public function moduleManifest(string $packageKey, ?string $ref = null): ModuleManifest
    {
        $manifest = $this->readManifest($packageKey, PackageKind::Module, $ref);
        if (! $manifest instanceof ModuleManifest) {
            throw new \InvalidArgumentException('Package key ['.$packageKey.'] is not a module.');
        }

        return $manifest;
    }

    public function extensionManifest(string $packageKey, ?string $ref = null): ExtensionManifest
    {
        $manifest = $this->readManifest($packageKey, PackageKind::Extension, $ref);
        if (! $manifest instanceof ExtensionManifest) {
            throw new \InvalidArgumentException('Package key ['.$packageKey.'] is not an extension.');
        }

        return $manifest;
    }

    public function remoteVersion(string $packageKey, PackageKind $kind, ?string $ref = null): string
    {
        return $this->manifests->read($this->packageDirectory($packageKey, $kind, $ref))['version'];
    }

    public function syncAvailableVersions(?string $ref = null): void
    {
        AgovenaPackage::query()
            ->where('source_type', PackageSourceType::Monorepo)
            ->each(function (AgovenaPackage $package) use ($ref): void {
                $key = (string) $package->composer_name;
                if ($key === '') {
                    return;
                }

                try {
                    $package->available_version = $this->remoteVersion(
                        $key,
                        $package->kind,
                        $ref ?? $this->resolvedRef($package),
                    );
                    $package->save();
                } catch (\Throwable) {
                    // Keep the last known available version when the remote cannot be resolved.
                }
            });
    }

    private function readManifest(string $packageKey, PackageKind $expectedKind, ?string $ref): ModuleManifest|ExtensionManifest
    {
        $directory = $this->packageDirectory($packageKey, $expectedKind, $ref);
        $data = $this->manifests->read($directory);

        if ($expectedKind === PackageKind::Module) {
            $file = $directory.DIRECTORY_SEPARATOR.'module.json';
            /** @var array<string, mixed> $json */
            $json = json_decode((string) File::get($file), true);

            return ModuleManifest::fromArray($json, $directory);
        }

        $file = $directory.DIRECTORY_SEPARATOR.'extension.json';
        /** @var array<string, mixed> $json */
        $json = json_decode((string) File::get($file), true);

        return ExtensionManifest::fromArray($json, $directory);
    }

    private function packageDirectory(string $packageKey, PackageKind $kind, ?string $ref): string
    {
        $mapping = $this->map->resolve($packageKey, $kind);
        $repository = $this->map->defaultRepository();
        $gitRef = $ref ?? (string) config('agovena.packages.monorepo.default_ref', 'main');
        if ($gitRef === '' || $gitRef === '*') {
            $gitRef = 'main';
        }

        return $this->checkout->resolve($repository, $gitRef, $mapping['path']);
    }

    private function resolvedRef(AgovenaPackage $package): string
    {
        $ref = (string) $package->version_constraint;
        if ($ref === '' || $ref === '*') {
            return (string) config('agovena.packages.monorepo.default_ref', 'main');
        }

        return $ref;
    }
}

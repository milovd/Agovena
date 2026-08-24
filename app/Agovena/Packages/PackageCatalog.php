<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionManifest;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Modules\ModuleManifest;
use App\Enums\PackageKind;
use App\Enums\PackageLifecycle;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Composer\Semver\Semver;

final class PackageCatalog
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ExtensionManager $extensions,
        private readonly PackageInstaller $installer,
        private readonly MonorepoRemoteCatalog $monorepo,
    ) {}

    /**
     * @return list<array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ModuleManifest|ExtensionManifest
     * }>
     */
    public function modules(): array
    {
        $this->monorepo->syncAvailableVersions();

        $rows = [];
        $seen = [];

        foreach ($this->modules->discover() as $manifest) {
            $seen[$manifest->id] = true;
            $rows[] = $this->moduleRow($manifest);
        }

        foreach ($this->monorepo->entries(PackageKind::Module) as $entry) {
            if (isset($seen[$entry['key']])) {
                continue;
            }

            try {
                $manifest = $this->monorepo->moduleManifest($entry['key']);
            } catch (\Throwable) {
                continue;
            }

            if (isset($seen[$manifest->id])) {
                continue;
            }

            $seen[$manifest->id] = true;
            $rows[] = $this->remoteModuleRow($entry['key'], $manifest);
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ModuleManifest|ExtensionManifest
     * }>
     */
    public function extensions(): array
    {
        $this->monorepo->syncAvailableVersions();

        $rows = [];
        $seen = [];

        foreach ($this->extensions->discover() as $manifest) {
            $seen[$manifest->id] = true;
            $rows[] = $this->extensionRow($manifest);
        }

        foreach ($this->monorepo->entries(PackageKind::Extension) as $entry) {
            if (isset($seen[$entry['key']])) {
                continue;
            }

            try {
                $manifest = $this->monorepo->extensionManifest($entry['key']);
            } catch (\Throwable) {
                continue;
            }

            if (isset($seen[$manifest->id])) {
                continue;
            }

            $seen[$manifest->id] = true;
            $rows[] = $this->remoteExtensionRow($entry['key'], $manifest);
        }

        return $rows;
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ModuleManifest
     * }
     */
    private function moduleRow(ModuleManifest $manifest): array
    {
        $status = $this->modules->status($manifest->id);
        $package = $this->packageRow(PackageKind::Module, $manifest->id);
        $compatible = $this->compatible($manifest->agovena);

        return [
            'kind' => PackageKind::Module,
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'source' => $this->resolvedSource($package),
            'composer_name' => $this->monorepoKey($package, $manifest->id),
            'lifecycle' => $this->lifecycle(
                installed: $status['installed'],
                enabled: $status['enabled'],
                wasDisabled: $status['record']?->disabled_at !== null,
                compatible: $compatible,
                updateAvailable: $package !== null && $this->installer->hasUpdate($package),
            ),
            'compatible' => $compatible,
            'compatibility_error' => $compatible ? null : __('admin.packages.incompatible', [
                'constraint' => $manifest->agovena,
                'platform' => (string) config('agovena.version', '0.1.0'),
            ]),
            'installed' => $status['installed'],
            'enabled' => $status['enabled'],
            'is_bundled' => false,
            'can_purge' => $this->canPurge($package),
            'on_disk' => true,
            'monorepo_key' => $this->monorepoKey($package, $manifest->id),
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ModuleManifest
     * }
     */
    private function remoteModuleRow(string $packageKey, ModuleManifest $manifest): array
    {
        $compatible = $this->compatible($manifest->agovena);

        return [
            'kind' => PackageKind::Module,
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'source' => PackageSourceType::Monorepo,
            'composer_name' => $packageKey,
            'lifecycle' => $compatible ? PackageLifecycle::Available : PackageLifecycle::Incompatible,
            'compatible' => $compatible,
            'compatibility_error' => $compatible ? null : __('admin.packages.incompatible', [
                'constraint' => $manifest->agovena,
                'platform' => (string) config('agovena.version', '0.1.0'),
            ]),
            'installed' => false,
            'enabled' => false,
            'is_bundled' => false,
            'can_purge' => false,
            'on_disk' => false,
            'monorepo_key' => $packageKey,
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ExtensionManifest
     * }
     */
    private function extensionRow(ExtensionManifest $manifest): array
    {
        $status = $this->extensions->status($manifest->id);
        $package = $this->packageRow(PackageKind::Extension, $manifest->id);
        $compatible = $status['compatible'];

        return [
            'kind' => PackageKind::Extension,
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'source' => $this->resolvedSource($package),
            'composer_name' => $this->monorepoKey($package, $manifest->id),
            'lifecycle' => $this->lifecycle(
                installed: $status['installed'],
                enabled: $status['enabled'],
                wasDisabled: $status['record']?->disabled_at !== null,
                compatible: $compatible,
                updateAvailable: $package !== null && $this->installer->hasUpdate($package),
            ),
            'compatible' => $compatible,
            'compatibility_error' => $status['compatibility_error'],
            'installed' => $status['installed'],
            'enabled' => $status['enabled'],
            'is_bundled' => false,
            'can_purge' => $this->canPurge($package),
            'on_disk' => true,
            'monorepo_key' => $this->monorepoKey($package, $manifest->id),
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     source: PackageSourceType,
     *     composer_name: string|null,
     *     lifecycle: PackageLifecycle,
     *     compatible: bool,
     *     compatibility_error: string|null,
     *     installed: bool,
     *     enabled: bool,
     *     is_bundled: bool,
     *     can_purge: bool,
     *     on_disk: bool,
     *     monorepo_key: string|null,
     *     manifest: ExtensionManifest
     * }
     */
    private function remoteExtensionRow(string $packageKey, ExtensionManifest $manifest): array
    {
        $compatible = $this->compatible($manifest->agovena);

        return [
            'kind' => PackageKind::Extension,
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'source' => PackageSourceType::Monorepo,
            'composer_name' => $packageKey,
            'lifecycle' => $compatible ? PackageLifecycle::Available : PackageLifecycle::Incompatible,
            'compatible' => $compatible,
            'compatibility_error' => $compatible ? null : __('admin.packages.incompatible', [
                'constraint' => $manifest->agovena,
                'platform' => (string) config('agovena.version', '0.1.0'),
            ]),
            'installed' => false,
            'enabled' => false,
            'is_bundled' => false,
            'can_purge' => false,
            'on_disk' => false,
            'monorepo_key' => $packageKey,
            'manifest' => $manifest,
        ];
    }

    private function packageRow(PackageKind $kind, string $id): ?AgovenaPackage
    {
        return AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $id)
            ->first();
    }

    private function monorepoKey(?AgovenaPackage $package, string $fallback): string
    {
        if ($package === null) {
            return $fallback;
        }

        return $package->composer_name ?? $fallback;
    }

    private function resolvedSource(?AgovenaPackage $package): PackageSourceType
    {
        if ($package instanceof AgovenaPackage) {
            return $package->source_type;
        }

        return OptionalPackagesPath::root() !== null
            ? PackageSourceType::Path
            : PackageSourceType::Monorepo;
    }

    private function compatible(string $constraint): bool
    {
        $platform = (string) config('agovena.version', '0.1.0');
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        try {
            return Semver::satisfies($platform, $constraint);
        } catch (\Throwable) {
            return false;
        }
    }

    private function lifecycle(bool $installed, bool $enabled, bool $wasDisabled, bool $compatible, bool $updateAvailable): PackageLifecycle
    {
        if (! $compatible) {
            return PackageLifecycle::Incompatible;
        }
        if ($updateAvailable) {
            return PackageLifecycle::UpdateAvailable;
        }
        if ($enabled) {
            return PackageLifecycle::Enabled;
        }
        if ($wasDisabled) {
            return PackageLifecycle::Disabled;
        }
        if ($installed) {
            return PackageLifecycle::Installed;
        }

        return PackageLifecycle::Available;
    }

    private function canPurge(?AgovenaPackage $package): bool
    {
        if ($package === null || $package->is_bundled) {
            return false;
        }

        return in_array($package->source_type, [
            PackageSourceType::Monorepo,
            PackageSourceType::Zip,
            PackageSourceType::Composer,
            PackageSourceType::Vcs,
        ], true);
    }
}

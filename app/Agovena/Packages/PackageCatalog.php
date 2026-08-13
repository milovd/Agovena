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
     *     manifest: ModuleManifest|ExtensionManifest
     * }>
     */
    public function modules(): array
    {
        $rows = [];
        foreach ($this->modules->discover() as $manifest) {
            $status = $this->modules->status($manifest->id);
            $package = $this->packageRow(PackageKind::Module, $manifest->id);
            $compatible = $this->compatible($manifest->agovena);
            $lifecycle = $this->lifecycle(
                installed: $status['installed'],
                enabled: $status['enabled'],
                wasDisabled: $status['record']?->disabled_at !== null,
                compatible: $compatible,
                updateAvailable: $package !== null && $this->installer->hasUpdate($package),
            );

            $rows[] = [
                'kind' => PackageKind::Module,
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'source' => $package instanceof AgovenaPackage ? $package->source_type : PackageSourceType::Bundled,
                'composer_name' => $package?->composer_name,
                'lifecycle' => $lifecycle,
                'compatible' => $compatible,
                'compatibility_error' => $compatible ? null : __('admin.packages.incompatible', [
                    'constraint' => $manifest->agovena,
                    'platform' => (string) config('agovena.version', '0.1.0'),
                ]),
                'installed' => $status['installed'],
                'enabled' => $status['enabled'],
                'is_bundled' => $package === null || $package->is_bundled,
                'can_purge' => $package !== null && ! $package->is_bundled,
                'manifest' => $manifest,
            ];
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
     *     manifest: ModuleManifest|ExtensionManifest
     * }>
     */
    public function extensions(): array
    {
        $rows = [];
        foreach ($this->extensions->discover() as $manifest) {
            $status = $this->extensions->status($manifest->id);
            $package = $this->packageRow(PackageKind::Extension, $manifest->id);
            $compatible = $status['compatible'];
            $lifecycle = $this->lifecycle(
                installed: $status['installed'],
                enabled: $status['enabled'],
                wasDisabled: $status['record']?->disabled_at !== null,
                compatible: $compatible,
                updateAvailable: $package !== null && $this->installer->hasUpdate($package),
            );

            $rows[] = [
                'kind' => PackageKind::Extension,
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'source' => $package instanceof AgovenaPackage ? $package->source_type : PackageSourceType::Bundled,
                'composer_name' => $package?->composer_name,
                'lifecycle' => $lifecycle,
                'compatible' => $compatible,
                'compatibility_error' => $status['compatibility_error'],
                'installed' => $status['installed'],
                'enabled' => $status['enabled'],
                'is_bundled' => $package === null || $package->is_bundled,
                'can_purge' => $package !== null && ! $package->is_bundled,
                'manifest' => $manifest,
            ];
        }

        return $rows;
    }

    private function packageRow(PackageKind $kind, string $id): ?AgovenaPackage
    {
        return AgovenaPackage::query()
            ->where('kind', $kind)
            ->where('agovena_id', $id)
            ->first();
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
}

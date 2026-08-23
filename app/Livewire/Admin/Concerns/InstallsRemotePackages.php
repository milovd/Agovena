<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Livewire\Concerns\RequiresRecentPassword;
use Illuminate\Validation\ValidationException;

trait InstallsRemotePackages
{
    use RequiresRecentPassword;

    public string $packageName = '';

    public string $versionConstraint = '*';

    public string $repositoryUrl = '';

    abstract protected function packageKind(): PackageKind;

    abstract protected function packageManagePermission(): string;

    public function installFromMonorepo(string $packageKey, PackageInstaller $installer): void
    {
        $this->authorize($this->packageManagePermission());

        try {
            $ref = (string) config('agovena.packages.monorepo.default_ref', 'main');
            if ($ref === '' || $ref === '*') {
                $ref = 'main';
            }

            $installer->install(new PackageSource(
                kind: $this->packageKind(),
                sourceType: PackageSourceType::Monorepo,
                locator: '',
                constraint: $ref,
                composerName: $packageKey,
            ));

            session()->flash('status', __('admin.packages.flash.installed', ['package' => $packageKey]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['package'][0] ?? collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }
    }

    public function installRemote(PackageInstaller $installer): void
    {
        $this->authorize($this->packageManagePermission());

        $this->validate([
            'packageName' => ['required', 'string', 'max:128'],
            'versionConstraint' => ['required', 'string', 'max:64'],
            'repositoryUrl' => ['nullable', 'string', 'max:255'],
        ]);

        $repository = trim($this->repositoryUrl);
        $sourceType = $repository === '' ? PackageSourceType::Composer : PackageSourceType::Vcs;

        try {
            $installer->install(new PackageSource(
                kind: $this->packageKind(),
                sourceType: $sourceType,
                locator: $sourceType === PackageSourceType::Vcs ? $repository : $this->packageName,
                constraint: $this->versionConstraint !== '' ? $this->versionConstraint : '*',
                composerName: $this->packageName,
            ));
            session()->flash('status', __('admin.packages.flash.installed', ['package' => $this->packageName]));
            $this->reset('packageName', 'versionConstraint', 'repositoryUrl');
            $this->versionConstraint = '*';
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['package'][0] ?? collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }
    }

    public function uninstallPackage(string $id, PackageInstaller $installer): void
    {
        $this->authorize($this->packageManagePermission());

        try {
            $installer->uninstall($this->packageKind(), $id, purgeFiles: false);
            session()->flash('status', __('admin.packages.flash.uninstalled', ['package' => $id]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['package'][0] ?? $e->getMessage());
        }
    }

    public function purgePackage(string $id, PackageInstaller $installer): void
    {
        $this->authorize($this->packageManagePermission());

        if (! $this->requireRecentPassword('purgePackage', ['id' => $id])) {
            return;
        }

        try {
            $installer->purge($this->packageKind(), $id);
            session()->flash('status', __('admin.packages.flash.purged', ['package' => $id]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['package'][0] ?? $e->getMessage());
        }
    }

    public function updatePackage(string $id, PackageInstaller $installer): void
    {
        $this->authorize($this->packageManagePermission());

        try {
            $installer->update($this->packageKind(), $id);
            session()->flash('status', __('admin.packages.flash.updated', ['package' => $id]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['package'][0] ?? $e->getMessage());
        }
    }
}

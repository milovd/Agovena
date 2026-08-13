<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\PackageKind;
use App\Livewire\Admin\Concerns\InstallsRemotePackages;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;
    use InstallsRemotePackages;

    public function mount(): void
    {
        $this->authorize('modules.view');
    }

    public function enable(string $moduleId, ModuleManager $modules, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('modules.manage');

        try {
            $modules->enable($moduleId);
            $sync(force: true);
            session()->flash('status', __('admin.modules.flash.enabled', ['module' => $moduleId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function disable(string $moduleId, ModuleManager $modules, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('modules.manage');

        try {
            $modules->disable($moduleId);
            $sync(force: true);
            session()->flash('status', __('admin.modules.flash.disabled', ['module' => $moduleId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function install(string $moduleId, ModuleManager $modules): void
    {
        $this->authorize('modules.manage');

        try {
            $modules->install($moduleId);
            session()->flash('status', __('admin.modules.flash.installed', ['module' => $moduleId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function render(AdminRegistrar $admin, PackageCatalog $catalog)
    {
        $grouped = [];
        foreach ($catalog->modules() as $row) {
            $group = $row['manifest']->group !== '' ? $row['manifest']->group : 'other';
            $grouped[$group][] = $row;
        }

        $order = ['commerce', 'recurring', 'experiences', 'other'];
        $ordered = [];
        foreach ($order as $group) {
            if (isset($grouped[$group])) {
                $ordered[$group] = $grouped[$group];
                unset($grouped[$group]);
            }
        }
        foreach ($grouped as $group => $rows) {
            $ordered[$group] = $rows;
        }

        return view('livewire.admin.modules.index', [
            'groups' => $ordered,
        ])->layout('layouts.admin', [
            'title' => __('admin.modules.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    protected function packageKind(): PackageKind
    {
        return PackageKind::Module;
    }

    protected function packageManagePermission(): string
    {
        return 'modules.manage';
    }
}

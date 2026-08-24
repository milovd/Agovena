<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Store\ApplyStorePresets;
use App\Agovena\Store\StorePresetCatalog;
use App\Enums\PackageKind;
use App\Livewire\Admin\Concerns\InstallsRemotePackages;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;
    use InstallsRemotePackages;

    #[Url(as: 'tab')]
    public string $tab = 'installed';

    /** @var list<string> */
    public array $selectedPresets = [];

    /** @var list<string> */
    public array $customModuleIds = [];

    public function mount(ApplyStorePresets $apply): void
    {
        $this->authorize('modules.view');
        $this->selectedPresets = $apply->selected();
        $this->customModuleIds = $apply->selectedModules();

        if (! in_array($this->tab, ['installed', 'available', 'custom'], true)) {
            $this->tab = 'installed';
        }
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

    public function applyConfiguration(ApplyStorePresets $apply): void
    {
        $this->authorize('modules.manage');
        try {
            $enabled = $apply->handle($this->selectedPresets, $this->customModuleIds);
            $this->selectedPresets = $apply->selected();
            $this->customModuleIds = $apply->selectedModules();
            session()->flash('status', __('admin.modules.flash.configuration_saved', [
                'modules' => implode(', ', $enabled),
            ]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function render(AdminRegistrar $admin, PackageCatalog $catalog, StorePresetCatalog $presets, ModuleManager $modules)
    {
        $groups = $this->orderGroups($this->groupModules($catalog->modules()));

        $presetRows = [];
        foreach ($presets->all() as $preset) {
            $presetRows[] = [
                'preset' => $preset,
                'modules' => array_map(
                    function (string $moduleId) use ($modules): string {
                        $manifest = $modules->manifest($moduleId);

                        return $manifest === null ? $moduleId : $manifest->name;
                    },
                    $preset->moduleIds,
                ),
            ];
        }

        return view('livewire.admin.modules.index', [
            'groups' => $groups,
            'installedGroups' => $this->filterGroups($groups, fn (array $row): bool => $row['installed']),
            'availableGroups' => $this->filterGroups($groups, fn (array $row): bool => ! $row['installed']),
            'presets' => $presetRows,
            'tabs' => [
                'installed' => __('admin.modules.tabs.installed'),
                'available' => __('admin.modules.tabs.available'),
                'custom' => __('admin.modules.tabs.custom'),
            ],
        ])->layout('layouts.admin', [
            'title' => __('admin.modules.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupModules(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $group = $row['manifest']->group !== '' ? $row['manifest']->group : 'other';
            $grouped[$group][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function orderGroups(array $grouped): array
    {
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

        return $ordered;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return array<string, list<array<string, mixed>>>
     */
    private function filterGroups(array $groups, callable $predicate): array
    {
        $filtered = [];
        foreach ($groups as $group => $rows) {
            $items = array_values(array_filter($rows, $predicate));
            if ($items !== []) {
                $filtered[$group] = $items;
            }
        }

        return $filtered;
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

<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Store\ApplyStorePresets;
use App\Agovena\Store\StorePreset;
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

    public function installPreset(string $presetId, ApplyStorePresets $apply, StorePresetCatalog $catalog): void
    {
        $this->authorize('modules.manage');

        $preset = $catalog->find($presetId);
        if ($preset === null) {
            session()->flash('error', __('admin.modules.preset_not_found'));

            return;
        }

        try {
            $enabled = $apply->installPreset($presetId, $this->selectedPresets, $this->customModuleIds);
            $this->selectedPresets = $apply->selected();
            $this->customModuleIds = $apply->selectedModules();
            session()->flash('status', __('admin.modules.flash.preset_installed', [
                'preset' => __($preset->labelKey),
                'modules' => $enabled !== []
                    ? implode(', ', $enabled)
                    : __('admin.modules.flash.preset_already_active'),
            ]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function uninstallPreset(string $presetId, ApplyStorePresets $apply, StorePresetCatalog $catalog, ModuleManager $modules): void
    {
        $this->authorize('modules.manage');

        $preset = $catalog->find($presetId);
        if ($preset === null) {
            session()->flash('error', __('admin.modules.preset_not_found'));

            return;
        }

        try {
            $existingPresetIds = $this->selectedPresets !== []
                ? $this->selectedPresets
                : [
                    ...$this->installedPresetIds($catalog, $modules),
                    ...($this->customModuleIds !== [] ? ['custom'] : []),
                ];

            if ($presetId === 'custom' && ! in_array('custom', $existingPresetIds, true) && $this->customModuleIds !== []) {
                $existingPresetIds[] = 'custom';
            }

            $disabled = $apply->uninstallPreset($presetId, $existingPresetIds, $this->customModuleIds);
            $this->selectedPresets = $apply->selected();
            $this->customModuleIds = $apply->selectedModules();

            session()->flash('status', __('admin.modules.flash.preset_uninstalled', [
                'preset' => __($preset->labelKey),
                'modules' => $disabled !== []
                    ? implode(', ', $disabled)
                    : __('admin.modules.flash.preset_uninstall_no_modules'),
            ]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function installCustomModule(string $moduleId, ApplyStorePresets $apply, ModuleManager $modules): void
    {
        $this->authorize('modules.manage');

        try {
            $added = $apply->installCustomModule($moduleId, $this->selectedPresets, $this->customModuleIds);
            $this->selectedPresets = $apply->selected();
            $this->customModuleIds = $apply->selectedModules();

            $manifest = $modules->manifest($moduleId);
            $name = $manifest !== null ? $manifest->name : $moduleId;
            session()->flash('status', $added
                ? __('admin.modules.flash.custom_module_installed', ['module' => $name])
                : __('admin.modules.flash.module_already_active', ['module' => $name]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['module'][0] ?? $e->getMessage());
        }
    }

    public function installAllCustomModules(ApplyStorePresets $apply, PackageCatalog $catalog, ModuleManager $modules): void
    {
        $this->authorize('modules.manage');

        $enabled = [];
        $firstError = null;

        foreach ($catalog->modules() as $row) {
            if (! $this->moduleRowCanBulkInstall($row)) {
                continue;
            }

            $moduleId = $row['manifest']->id;

            try {
                if (! $row['installed']) {
                    $modules->install($moduleId);
                }

                if ($apply->installCustomModule($moduleId, $this->selectedPresets, $this->customModuleIds)) {
                    $manifest = $modules->manifest($moduleId);
                    $enabled[] = $manifest !== null ? $manifest->name : $moduleId;
                }

                $this->selectedPresets = $apply->selected();
                $this->customModuleIds = $apply->selectedModules();
            } catch (ValidationException $e) {
                $firstError ??= $e->errors()['module'][0] ?? $e->getMessage();
            }
        }

        if ($enabled !== []) {
            session()->flash('status', __('admin.modules.flash.install_all', [
                'modules' => implode(', ', $enabled),
            ]));
        } elseif ($firstError !== null) {
            session()->flash('error', $firstError);
        } else {
            session()->flash('status', __('admin.modules.flash.install_all_none'));
        }
    }

    public function render(AdminRegistrar $admin, PackageCatalog $catalog, StorePresetCatalog $presets, ModuleManager $modules)
    {
        $groups = $this->orderGroups($this->groupModules($catalog->modules()));
        $catalogById = $this->catalogModulesById($catalog);

        $availablePresets = [];
        $installedPresetRows = [];
        $installedPresetIds = $this->installedPresetIds($presets, $modules);

        $hasCustomModules = $this->customModuleIds !== [] || in_array('custom', $this->selectedPresets, true);
        $customModuleRows = [];
        if ($this->customModuleIds !== []) {
            foreach ($catalog->modules() as $moduleRow) {
                if (in_array($moduleRow['manifest']->id, $this->customModuleIds, true)) {
                    $customModuleRows[] = $this->enrichModuleRow(
                        $moduleRow,
                        $moduleRow['manifest']->id,
                        'custom',
                        $installedPresetIds,
                        $presets,
                    );
                }
            }
        }

        $availableCustomPresetRow = $this->buildAvailableCustomPresetRow($presets, $groups, $installedPresetIds);

        foreach ($presets->all() as $preset) {
            if ($preset->isCustom) {
                continue;
            }

            $row = $this->buildPresetRow($preset, $modules, $catalogById);

            if (in_array($preset->id, $installedPresetIds, true)) {
                $row['moduleRows'] = array_map(
                    fn (array $moduleRow): array => $this->enrichModuleRow(
                        $moduleRow,
                        $moduleRow['manifest']->id,
                        $preset->id,
                        $installedPresetIds,
                        $presets,
                    ),
                    $row['moduleRows'],
                );
                $row['uninstallConfirm'] = $this->buildPresetUninstallConfirm(
                    $preset,
                    $this->presetUninstallSummary($preset, $installedPresetIds, $presets, $modules, $this->customModuleIds),
                );
                $installedPresetRows[] = $row;
            } else {
                $availablePresets[] = $row;
            }
        }

        $customUninstallConfirm = null;
        if ($hasCustomModules) {
            $customPreset = $presets->find('custom');
            if ($customPreset !== null) {
                $customUninstallConfirm = $this->buildPresetUninstallConfirm(
                    $customPreset,
                    $this->presetUninstallSummary($customPreset, $installedPresetIds, $presets, $modules, $this->customModuleIds),
                );
            }
        }

        return view('livewire.admin.modules.index', [
            'groups' => $groups,
            'availablePresets' => $availablePresets,
            'availableCustomPresetRow' => $availableCustomPresetRow,
            'installedPresetRows' => $installedPresetRows,
            'hasCustomModules' => $hasCustomModules,
            'customModuleRows' => $customModuleRows,
            'customUninstallConfirm' => $customUninstallConfirm,
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
     * @param  list<string>  $installedPresetIds
     * @return array<string, mixed>|null
     */
    private function buildAvailableCustomPresetRow(
        StorePresetCatalog $catalog,
        array $groups,
        array $installedPresetIds,
    ): ?array {
        $preset = $catalog->find('custom');
        if ($preset === null || $groups === []) {
            return null;
        }

        $moduleGroups = [];
        $moduleCount = 0;

        foreach ($groups as $group => $rows) {
            $moduleGroups[$group] = array_map(
                fn (array $moduleRow): array => $this->enrichModuleRow(
                    $moduleRow,
                    $moduleRow['manifest']->id,
                    'custom',
                    $installedPresetIds,
                    $catalog,
                ),
                $rows,
            );
            $moduleCount += count($moduleGroups[$group]);
        }

        return [
            'preset' => $preset,
            'moduleGroups' => $moduleGroups,
            'moduleCount' => $moduleCount,
            'canInstallAll' => $this->customPresetCanInstallAll($moduleGroups),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $moduleGroups
     */
    private function customPresetCanInstallAll(array $moduleGroups): bool
    {
        foreach ($moduleGroups as $rows) {
            foreach ($rows as $row) {
                if ($this->moduleRowCanBulkInstall($row)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function moduleRowCanBulkInstall(array $row): bool
    {
        return ($row['compatible'] ?? false)
            && ! ($row['enabled'] ?? false)
            && ($row['on_disk'] ?? false);
    }

    /**
     * @return list<string>
     */
    private function installedPresetIds(StorePresetCatalog $catalog, ModuleManager $modules): array
    {
        if ($this->selectedPresets !== []) {
            return array_values(array_filter(
                $this->selectedPresets,
                fn (string $id): bool => $catalog->find($id) !== null && $id !== 'custom',
            ));
        }

        $inferred = [];
        foreach ($catalog->all() as $preset) {
            if ($preset->isCustom || $preset->moduleIds === []) {
                continue;
            }

            $enabledCount = 0;
            foreach ($preset->moduleIds as $moduleId) {
                if ($modules->isEnabled($moduleId)) {
                    $enabledCount++;
                }
            }

            if ($enabledCount > 0 && $enabledCount === count($preset->moduleIds)) {
                $inferred[] = $preset->id;
            }
        }

        return $inferred;
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogById
     * @return array<string, mixed>
     */
    private function buildPresetRow(StorePreset $preset, ModuleManager $modules, array $catalogById): array
    {
        $moduleRows = [];
        $displayModules = [];
        foreach ($preset->moduleIds as $moduleId) {
            if (isset($catalogById[$moduleId])) {
                $moduleRows[] = $catalogById[$moduleId];
            }

            $manifest = $modules->manifest($moduleId);
            $displayModules[] = [
                'id' => $moduleId,
                'name' => $manifest !== null ? $manifest->name : $moduleId,
                'description' => $manifest !== null ? $manifest->description : '',
            ];
        }

        $status = $this->presetStatus($preset, $modules);

        return [
            'preset' => $preset,
            'modules' => $displayModules,
            'moduleRows' => $moduleRows,
            'status' => $status,
            'statusLabel' => 'admin.modules.preset_status.'.$status,
            'canInstallSetup' => $status !== 'active' && $this->presetCanInstall($moduleRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $moduleRows
     */
    private function presetCanInstall(array $moduleRows): bool
    {
        foreach ($moduleRows as $row) {
            if (! ($row['compatible'] ?? false)) {
                continue;
            }

            if (! ($row['installed'] ?? false) || ! ($row['enabled'] ?? false)) {
                return true;
            }
        }

        return $moduleRows === [];
    }

    private function presetStatus(StorePreset $preset, ModuleManager $modules): string
    {
        if ($preset->moduleIds === []) {
            return 'active';
        }

        $enabled = 0;
        $installed = 0;
        foreach ($preset->moduleIds as $moduleId) {
            if ($modules->isEnabled($moduleId)) {
                $enabled++;
            }
            if ($modules->isInstalled($moduleId)) {
                $installed++;
            }
        }

        if ($enabled === count($preset->moduleIds)) {
            return 'active';
        }

        if ($installed > 0 || $enabled > 0) {
            return 'partial';
        }

        return 'missing';
    }

    /**
     * @param  list<string>  $installedPresetIds
     * @return array<string, mixed>
     */
    private function enrichModuleRow(
        array $moduleRow,
        string $moduleId,
        string $currentPresetId,
        array $installedPresetIds,
        StorePresetCatalog $catalog,
    ): array {
        $labels = [];
        foreach ($catalog->presetIdsForModule($moduleId) as $presetId) {
            if ($presetId === $currentPresetId) {
                continue;
            }

            if (! in_array($presetId, $installedPresetIds, true)) {
                continue;
            }

            $preset = $catalog->find($presetId);
            if ($preset !== null) {
                $labels[] = __($preset->labelKey);
            }
        }

        $moduleRow['otherPresetLabels'] = $labels;

        return $moduleRow;
    }

    /**
     * @param  list<string>  $installedPresetIds
     * @param  list<string>  $customModuleIds
     * @return array{disable: list<string>, keep: list<string>}
     */
    private function presetUninstallSummary(
        StorePreset $preset,
        array $installedPresetIds,
        StorePresetCatalog $catalog,
        ModuleManager $modules,
        array $customModuleIds,
    ): array {
        $remaining = array_values(array_filter(
            $installedPresetIds,
            fn (string $id): bool => $id !== $preset->id,
        ));

        $remainingCustomModules = $preset->isCustom ? [] : $customModuleIds;

        $stillNeeded = array_flip(array_values(array_unique(array_merge(
            $catalog->moduleIdsFor(array_values(array_filter(
                $remaining,
                fn (string $id): bool => $id !== 'custom',
            ))),
            $remainingCustomModules,
        ))));

        $disable = [];
        $keep = [];
        $moduleIds = $preset->isCustom ? $customModuleIds : $preset->moduleIds;

        foreach ($moduleIds as $moduleId) {
            $manifest = $modules->manifest($moduleId);
            $name = $manifest !== null ? $manifest->name : $moduleId;

            if (isset($stillNeeded[$moduleId])) {
                $keep[] = $name;

                continue;
            }

            if ($modules->isEnabled($moduleId)) {
                $disable[] = $name;
            }
        }

        return ['disable' => $disable, 'keep' => $keep];
    }

    /**
     * @param  array{disable: list<string>, keep: list<string>}  $summary
     */
    private function buildPresetUninstallConfirm(StorePreset $preset, array $summary): string
    {
        $lines = [__('admin.modules.uninstall_preset_confirm', ['preset' => __($preset->labelKey)])];

        if ($summary['disable'] !== []) {
            $lines[] = __('admin.modules.uninstall_preset_disable', [
                'modules' => implode(', ', $summary['disable']),
            ]);
        }

        if ($summary['keep'] !== []) {
            $lines[] = __('admin.modules.uninstall_preset_keep', [
                'modules' => implode(', ', $summary['keep']),
            ]);
        }

        return implode("\n\n", $lines);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogModulesById(PackageCatalog $catalog): array
    {
        $byId = [];
        foreach ($catalog->modules() as $row) {
            $byId[$row['manifest']->id] = $row;
        }

        return $byId;
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

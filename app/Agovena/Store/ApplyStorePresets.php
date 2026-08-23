<?php

declare(strict_types=1);

namespace App\Agovena\Store;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;

final class ApplyStorePresets
{
    public function __construct(
        private readonly StorePresetCatalog $catalog,
        private readonly ModuleManager $modules,
        private readonly SettingsRepository $settings,
        private readonly SyncRegisteredPermissions $syncPermissions,
    ) {}

    /**
     * Enable the union of selected preset bundles and individually selected Modules.
     * Presets are only a UI composition layer; ModuleManager remains the source of truth.
     *
     * @param  list<string>  $presetIds
     * @param  list<string>  $customModuleIds
     * @return list<string> Enabled module ids from this apply
     */
    public function handle(array $presetIds, array $customModuleIds = []): array
    {
        $selectedPresets = $this->validPresetIds($presetIds);
        $selectedModules = array_values(array_unique(array_merge(
            $this->catalog->moduleIdsFor($selectedPresets),
            array_values(array_filter($customModuleIds, 'is_string')),
        )));

        $this->settings->set('store', 'presets', $selectedPresets);
        $this->settings->set('store', 'custom_modules', array_values(array_filter($customModuleIds, 'is_string')));

        $enabled = [];
        foreach ($selectedModules as $moduleId) {
            if ($this->modules->manifest($moduleId) === null) {
                continue;
            }
            if (! $this->modules->isInstalled($moduleId)) {
                $this->modules->install($moduleId);
            }
            $this->modules->enable($moduleId);
            $enabled[] = $moduleId;
        }

        if ($enabled !== []) {
            ($this->syncPermissions)(force: true);
        }

        return $enabled;
    }

    /** @return list<string> */
    public function selected(): array
    {
        $stored = $this->settings->get('store', 'presets', []);
        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_filter(
            $stored,
            fn (mixed $id): bool => is_string($id) && $this->catalog->find($id) !== null,
        ));
    }

    /** @return list<string> */
    public function selectedModules(): array
    {
        $stored = $this->settings->get('store', 'custom_modules', []);

        return is_array($stored)
            ? array_values(array_filter($stored, 'is_string'))
            : [];
    }

    /** @param list<string> $presetIds */
    private function validPresetIds(array $presetIds): array
    {
        $selected = [];
        foreach ($presetIds as $id) {
            if ($this->catalog->find($id) !== null) {
                $selected[] = $id;
            }
        }

        return array_values(array_unique($selected));
    }
}

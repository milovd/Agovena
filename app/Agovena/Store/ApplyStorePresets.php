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
     * Enable the union of Modules for the selected presets. Never disables Modules.
     *
     * @param  list<string>  $presetIds
     * @return list<string> Enabled module ids from this apply
     */
    public function handle(array $presetIds): array
    {
        $selected = [];
        foreach ($presetIds as $id) {
            if ($this->catalog->find($id) !== null) {
                $selected[] = $id;
            }
        }
        $selected = array_values(array_unique($selected));

        $this->settings->set('store', 'presets', $selected);

        $enabled = [];
        foreach ($this->catalog->moduleIdsFor($selected) as $moduleId) {
            if ($this->modules->manifest($moduleId) === null) {
                continue;
            }
            $this->modules->enable($moduleId);
            $enabled[] = $moduleId;
        }

        if ($enabled !== []) {
            ($this->syncPermissions)(force: true);
        }

        return $enabled;
    }

    /**
     * @return list<string>
     */
    public function selected(): array
    {
        $stored = $this->settings->get('store', 'presets', []);
        if (! is_array($stored)) {
            return [];
        }

        $ids = [];
        foreach ($stored as $id) {
            if (is_string($id) && $this->catalog->find($id) !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}

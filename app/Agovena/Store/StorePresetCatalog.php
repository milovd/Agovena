<?php

declare(strict_types=1);

namespace App\Agovena\Store;

final class StorePresetCatalog
{
    /**
     * Convenience bundles above Modules. Never a permanent store type.
     *
     * @return list<StorePreset>
     */
    public function all(): array
    {
        return [
            new StorePreset(
                id: 'physical',
                labelKey: 'admin.store_presets.physical',
                ledeKey: 'admin.store_presets.physical_lede',
                moduleIds: ['inventory', 'shipping'],
            ),
            new StorePreset(
                id: 'digital',
                labelKey: 'admin.store_presets.digital',
                ledeKey: 'admin.store_presets.digital_lede',
                moduleIds: ['digital-delivery', 'subscriptions'],
            ),
            new StorePreset(
                id: 'downloadable',
                labelKey: 'admin.store_presets.downloadable',
                ledeKey: 'admin.store_presets.downloadable_lede',
                moduleIds: ['digital', 'subscriptions'],
            ),
            new StorePreset(
                id: 'hosting',
                labelKey: 'admin.store_presets.hosting',
                ledeKey: 'admin.store_presets.hosting_lede',
                moduleIds: ['provisioning', 'subscriptions'],
            ),
            new StorePreset(
                id: 'subscriptions',
                labelKey: 'admin.store_presets.subscriptions',
                ledeKey: 'admin.store_presets.subscriptions_lede',
                moduleIds: ['subscriptions'],
            ),
            new StorePreset(
                id: 'events',
                labelKey: 'admin.store_presets.events',
                ledeKey: 'admin.store_presets.events_lede',
                moduleIds: ['events'],
            ),
            new StorePreset(
                id: 'custom',
                labelKey: 'admin.store_presets.custom',
                ledeKey: 'admin.store_presets.custom_lede',
                moduleIds: [],
                isCustom: true,
            ),
        ];
    }

    public function find(string $id): ?StorePreset
    {
        foreach ($this->all() as $preset) {
            if ($preset->id === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $presetIds
     * @return list<string>
     */
    public function moduleIdsFor(array $presetIds): array
    {
        $modules = [];
        foreach ($presetIds as $id) {
            $preset = $this->find($id);
            if ($preset === null) {
                continue;
            }
            foreach ($preset->moduleIds as $moduleId) {
                $modules[$moduleId] = true;
            }
        }

        return array_keys($modules);
    }

    /**
     * @return list<string>
     */
    public function presetIdsForModule(string $moduleId): array
    {
        $presetIds = [];
        foreach ($this->all() as $preset) {
            if (in_array($moduleId, $preset->moduleIds, true)) {
                $presetIds[] = $preset->id;
            }
        }

        return $presetIds;
    }
}

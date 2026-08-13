<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Store;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Store\ApplyStorePresets;
use App\Agovena\Store\StorePresetCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Presets extends Component
{
    use AuthorizesRequests;

    /** @var list<string> */
    public array $selected = [];

    public function mount(ApplyStorePresets $apply): void
    {
        $this->authorize('modules.manage');
        $this->selected = $apply->selected();
    }

    public function apply(ApplyStorePresets $apply): void
    {
        $this->authorize('modules.manage');
        $enabled = $apply->handle($this->selected);
        $this->selected = $apply->selected();

        session()->flash(
            'status',
            $enabled === []
                ? __('admin.store_presets.applied_none')
                : __('admin.store_presets.applied', ['modules' => implode(', ', $enabled)]),
        );
    }

    public function render(AdminRegistrar $admin, StorePresetCatalog $catalog, ModuleManager $modules)
    {
        $rows = [];
        foreach ($catalog->all() as $preset) {
            $moduleLabels = [];
            foreach ($preset->moduleIds as $moduleId) {
                $manifest = $modules->manifest($moduleId);
                $moduleLabels[] = $manifest === null ? $moduleId : $manifest->name;
            }
            $rows[] = [
                'preset' => $preset,
                'modules' => $moduleLabels,
            ];
        }

        $enabled = [];
        foreach ($modules->discover() as $manifest) {
            if ($modules->isEnabled($manifest->id)) {
                $enabled[] = $manifest->name;
            }
        }

        return view('livewire.admin.store.presets', [
            'rows' => $rows,
            'enabledModules' => $enabled,
        ])->layout('layouts.admin', [
            'title' => __('admin.store_presets.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

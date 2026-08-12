<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\SettingsGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Hub extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('settings.view');
    }

    public function render(AdminRegistrar $admin)
    {
        $staff = auth('staff')->user();

        $groups = collect($admin->settingsGroups())
            ->filter(function (SettingsGroup $group) use ($staff): bool {
                return $group->permission === null
                    || ($staff !== null && $staff->can($group->permission));
            })
            ->values();

        return view('livewire.admin.settings.hub', [
            'groups' => $groups,
        ])->layout('layouts.admin', [
            'title' => __('admin.settings.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

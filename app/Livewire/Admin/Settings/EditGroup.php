<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Deep-link compatibility for /admin/settings/{group}.
 * Settings are edited on the tabbed Hub.
 */
final class EditGroup extends Component
{
    use AuthorizesRequests;

    public function mount(string $group): void
    {
        $this->authorize('settings.view');

        $this->redirect(route('admin.settings.index', ['tab' => $group]), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.settings.edit-group');
    }
}

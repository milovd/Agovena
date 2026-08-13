<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Installation\ApplicationSchemaStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Updates extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('settings.view');
    }

    public function render(AdminRegistrar $admin, ApplicationSchemaStatus $schema)
    {
        $schema->refresh();

        return view('livewire.admin.system.updates', $schema->viewData())->layout('layouts.admin', [
            'title' => __('admin.updates.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

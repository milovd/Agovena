<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Operations\SystemOperationsStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Updates extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('settings.view');
    }

    public function render(AdminRegistrar $admin, ApplicationSchemaStatus $schema, SystemOperationsStatus $operations)
    {
        $schema->refresh();

        return view('livewire.admin.system.updates', $operations->viewData())->layout('layouts.admin', [
            'title' => __('admin.updates.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

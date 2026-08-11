<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Dashboard extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        return view('livewire.admin.dashboard', [
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Dashboard',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.audit.index', [
            'logs' => AuditLog::query()->latest('id')->paginate(50),
        ])->layout('layouts.admin', [
            'title' => __('admin.audit.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

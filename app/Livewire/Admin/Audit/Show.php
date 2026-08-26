<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public AuditLog $auditLog;

    public function mount(AuditLog $auditLog): void
    {
        $this->authorize('audit.view');
        $this->auditLog = $auditLog;
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.audit.show', [
            'auditLog' => $this->auditLog,
        ])->layout('layouts.admin', [
            'title' => __('admin.audit.detail_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

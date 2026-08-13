<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Notifications;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\EmailLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class EmailLogIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('notifications.view');
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.notifications.email-log', [
            'logs' => EmailLog::query()->latest('id')->paginate(30),
        ])->layout('layouts.admin', [
            'title' => __('admin.email_log.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Notifications;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Notifications\NotificationTemplateCatalog;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public ?string $confirmingRemove = null;

    public function mount(): void
    {
        $this->authorize('notifications.view');
    }

    public function confirmRemove(string $key, NotificationTemplateCatalog $catalog): void
    {
        $this->authorize('notifications.manage');
        abort_unless($catalog->find($key) !== null, 404);

        $this->confirmingRemove = $key;
    }

    public function cancelRemove(): void
    {
        $this->confirmingRemove = null;
    }

    public function remove(NotificationTemplateCatalog $catalog): void
    {
        $this->authorize('notifications.manage');

        if ($this->confirmingRemove === null) {
            return;
        }

        abort_unless($catalog->find($this->confirmingRemove) !== null, 404);

        NotificationTemplate::query()
            ->where('key', $this->confirmingRemove)
            ->delete();

        session()->flash('status', __('admin.notifications.removed'));
        $this->confirmingRemove = null;
    }

    public function render(AdminRegistrar $admin, NotificationTemplateCatalog $catalog)
    {
        $definitions = $catalog->all();
        $templates = NotificationTemplate::query()
            ->whereIn('key', array_map(static fn ($definition): string => $definition->key, $definitions))
            ->get()
            ->keyBy('key');

        return view('livewire.admin.notifications.index', [
            'definitions' => $definitions,
            'templates' => $templates,
            'confirmingDefinition' => $this->confirmingRemove === null
                ? null
                : $catalog->find($this->confirmingRemove),
        ])->layout('layouts.admin', [
            'title' => __('admin.notifications.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}

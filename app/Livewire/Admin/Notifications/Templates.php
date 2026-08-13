<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Notifications;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Notifications\NotificationTemplateCatalog;
use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Templates extends Component
{
    use AuthorizesRequests;

    public string $selected = 'order_placed';

    public string $subject = '';

    public string $body = '';

    public bool $enabled = true;

    public function mount(NotificationTemplateCatalog $catalog): void
    {
        $this->authorize('notifications.view');
        $definitions = $catalog->all();
        $this->selected = $definitions[0]->key;
        $this->loadSelected(app(RendersNotificationMail::class));
    }

    public function select(string $key, NotificationTemplateCatalog $catalog, RendersNotificationMail $renderer): void
    {
        $this->authorize('notifications.view');
        abort_unless($catalog->find($key) !== null, 404);
        $this->selected = $key;
        $this->loadSelected($renderer);
        $this->resetErrorBag();
    }

    public function resetToDefault(RendersNotificationMail $renderer): void
    {
        $this->authorize('notifications.manage');
        $defaults = $renderer->editableDefaults($this->selected);
        $this->subject = $defaults['subject'];
        $this->body = $defaults['body'];
        $this->enabled = true;
    }

    public function save(NotificationTemplateCatalog $catalog): void
    {
        $this->authorize('notifications.manage');
        abort_unless($catalog->find($this->selected) !== null, 404);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'enabled' => ['boolean'],
        ]);

        NotificationTemplate::query()->updateOrCreate(
            ['key' => $this->selected],
            [
                'subject' => $this->subject,
                'body' => $this->body,
                'enabled' => $this->enabled,
            ],
        );

        session()->flash('status', __('admin.notifications.saved'));
    }

    public function render(AdminRegistrar $admin, NotificationTemplateCatalog $catalog)
    {
        $definition = $catalog->find($this->selected);

        return view('livewire.admin.notifications.templates', [
            'definitions' => $catalog->all(),
            'definition' => $definition,
            'placeholderList' => $definition === null
                ? ''
                : collect($definition->placeholders)->map(static fn (string $name): string => '{{'.$name.'}}')->implode(' '),
        ])->layout('layouts.admin', [
            'title' => __('admin.notifications.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function loadSelected(RendersNotificationMail $renderer): void
    {
        $defaults = $renderer->editableDefaults($this->selected);
        $row = NotificationTemplate::query()->where('key', $this->selected)->first();
        $this->subject = filled($row?->subject) ? (string) $row->subject : $defaults['subject'];
        $this->body = filled($row?->body) ? (string) $row->body : $defaults['body'];
        $this->enabled = $row === null || $row->enabled;
    }
}

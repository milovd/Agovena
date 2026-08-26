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

    public string $tab = 'mail';

    public string $subject = '';

    public string $body = '';

    public string $mailFormat = 'plain';

    public string $notificationTitle = '';

    public string $notificationBody = '';

    public bool $enabled = true;

    public bool $mailEnabled = true;

    public bool $notificationEnabled = true;

    public bool $pushEnabled = true;

    public bool $userChoice = false;

    public bool $isCreate = false;

    public function mount(NotificationTemplateCatalog $catalog, ?string $key = null): void
    {
        $this->authorize('notifications.view');
        $definitions = $catalog->all();
        $this->isCreate = request()->routeIs('admin.notifications.create');
        $this->selected = $key ?? $definitions[0]->key;
        abort_unless($catalog->find($this->selected) !== null, 404);
        $this->loadSelected(app(RendersNotificationMail::class));
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, ['mail', 'notifications'], true), 404);
        $this->tab = $tab;
    }

    public function select(string $key, NotificationTemplateCatalog $catalog, RendersNotificationMail $renderer): void
    {
        $this->authorize('notifications.view');
        abort_unless($catalog->find($key) !== null, 404);
        $this->selected = $key;
        $this->loadSelected($renderer);
        $this->resetErrorBag();
    }

    public function updatedSelected(string $key, NotificationTemplateCatalog $catalog, RendersNotificationMail $renderer): void
    {
        if (! $this->isCreate) {
            return;
        }

        abort_unless($catalog->find($key) !== null, 404);
        $this->loadSelected($renderer);
        $this->resetErrorBag();
    }

    public function resetToDefault(RendersNotificationMail $renderer): void
    {
        $this->authorize('notifications.manage');
        $defaults = $renderer->editableDefaults($this->selected);
        $this->subject = $defaults['subject'];
        $this->body = $defaults['body'];
        $this->mailFormat = 'plain';
        $this->notificationTitle = '';
        $this->notificationBody = '';
        $this->enabled = true;
        $this->mailEnabled = true;
        $this->notificationEnabled = true;
        $this->pushEnabled = true;
        $this->userChoice = false;
    }

    public function save(NotificationTemplateCatalog $catalog): void
    {
        $this->authorize('notifications.manage');
        abort_unless($catalog->find($this->selected) !== null, 404);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'notificationTitle' => ['nullable', 'string', 'max:255'],
            'notificationBody' => ['nullable', 'string', 'max:5000'],
            'mailFormat' => ['required', 'in:plain,markdown,html'],
            'enabled' => ['boolean'],
            'mailEnabled' => ['boolean'],
            'notificationEnabled' => ['boolean'],
            'pushEnabled' => ['boolean'],
            'userChoice' => ['boolean'],
        ]);

        NotificationTemplate::query()->updateOrCreate(
            ['key' => $this->selected],
            [
                'subject' => $this->subject,
                'body' => $this->body,
                'mail_format' => $this->mailFormat,
                'notification_title' => filled($this->notificationTitle) ? $this->notificationTitle : null,
                'notification_body' => filled($this->notificationBody) ? $this->notificationBody : null,
                'enabled' => $this->enabled && $this->mailEnabled,
                'mail_enabled' => $this->mailEnabled,
                'in_app_enabled' => $this->notificationEnabled,
                'push_enabled' => $this->pushEnabled,
                'user_choice' => $this->userChoice,
            ],
        );

        session()->flash('status', __('admin.notifications.saved'));

        if (request()->routeIs('admin.notifications.create', 'admin.notifications.edit')) {
            $this->redirectRoute('admin.notifications');
        }
    }

    public function render(AdminRegistrar $admin, NotificationTemplateCatalog $catalog)
    {
        $definition = $catalog->find($this->selected);

        return view('livewire.admin.notifications.form', [
            'definition' => $definition,
            'tabs' => [
                'mail' => __('admin.notifications.tabs.mail'),
                'notifications' => __('admin.notifications.tabs.notifications'),
            ],
            'placeholderList' => $definition === null
                ? ''
                : collect($definition->placeholders)->map(static fn (string $name): string => '{{'.$name.'}}')->implode(' '),
            'definitions' => $catalog->all(),
        ])->layout('layouts.admin', [
            'title' => $this->isCreate ? __('admin.notifications.create_title') : __('admin.notifications.edit_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function loadSelected(RendersNotificationMail $renderer): void
    {
        $defaults = $renderer->editableDefaults($this->selected);
        $row = NotificationTemplate::query()->where('key', $this->selected)->first();
        if (! $row instanceof NotificationTemplate) {
            $this->subject = $defaults['subject'];
            $this->body = $defaults['body'];
            $this->mailFormat = 'plain';
            $this->notificationTitle = '';
            $this->notificationBody = '';
            $this->enabled = true;
            $this->mailEnabled = true;
            $this->notificationEnabled = true;
            $this->pushEnabled = true;
            $this->userChoice = false;

            return;
        }

        $this->subject = filled($row->subject) ? (string) $row->subject : $defaults['subject'];
        $this->body = filled($row->body) ? (string) $row->body : $defaults['body'];
        $this->mailFormat = in_array($row->mail_format, ['plain', 'markdown', 'html'], true) ? (string) $row->mail_format : 'plain';
        $this->notificationTitle = (string) ($row->notification_title ?? '');
        $this->notificationBody = (string) ($row->notification_body ?? '');
        $this->enabled = $row->enabled;
        $this->mailEnabled = $row->mail_enabled && $row->enabled;
        $this->notificationEnabled = $row->in_app_enabled;
        $this->pushEnabled = $row->push_enabled;
        $this->userChoice = $row->user_choice;
    }
}

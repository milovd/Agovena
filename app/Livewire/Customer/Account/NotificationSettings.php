<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Notifications\NotificationTemplateCatalog;
use App\Agovena\Notifications\NotificationTemplateDefinition;
use App\Agovena\Notifications\VapidKeyStore;
use App\Agovena\Theme\ThemeManager;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use Livewire\Component;

final class NotificationSettings extends Component
{
    /** @var array<string, array{in_app_enabled: bool, push_enabled: bool, mail_enabled: bool}> */
    public array $preferences = [];

    public function mount(NotificationTemplateCatalog $catalog): void
    {
        $user = current_user();
        abort_unless($user !== null, 403);

        $saved = $user->notificationPreferences()->get()->keyBy('key');
        foreach ($catalog->all() as $definition) {
            if ($saved->has($definition->key)) {
                /** @var NotificationPreference $preference */
                $preference = $saved->get($definition->key);
                $this->preferences[$definition->key] = [
                    'in_app_enabled' => $preference->in_app_enabled,
                    'push_enabled' => $preference->push_enabled,
                    'mail_enabled' => $preference->mail_enabled,
                ];

                continue;
            }

            $this->preferences[$definition->key] = [
                'in_app_enabled' => true,
                'push_enabled' => true,
                'mail_enabled' => true,
            ];
        }
    }

    public function savePreferences(NotificationTemplateCatalog $catalog): void
    {
        $user = current_user();
        abort_unless($user !== null, 403);

        $keys = array_map(
            static fn (NotificationTemplateDefinition $definition): string => $definition->key,
            $catalog->all(),
        );
        $this->validate([
            'preferences' => ['array'],
            'preferences.*.in_app_enabled' => ['required', 'boolean'],
            'preferences.*.push_enabled' => ['required', 'boolean'],
            'preferences.*.mail_enabled' => ['required', 'boolean'],
        ]);

        foreach ($keys as $key) {
            $values = $this->preferences[$key] ?? null;
            if (! is_array($values)) {
                continue;
            }

            $template = NotificationTemplate::query()->where('key', $key)->first();
            if ($template instanceof NotificationTemplate && ! $template->user_choice) {
                continue;
            }

            $user->notificationPreferences()->updateOrCreate(
                ['key' => $key],
                [
                    'in_app_enabled' => (bool) $values['in_app_enabled'],
                    'push_enabled' => (bool) $values['push_enabled'],
                    'mail_enabled' => (bool) $values['mail_enabled'],
                ],
            );
        }

        session()->flash('status', __('customer.notifications.saved'));
    }

    public function render(ThemeManager $themes, NotificationTemplateCatalog $catalog, VapidKeyStore $vapid)
    {
        $theme = $themes->active();
        $user = current_user();
        abort_unless($user !== null, 403);
        $vapidConfig = $vapid->get();
        $keys = array_map(
            static fn (NotificationTemplateDefinition $definition): string => $definition->key,
            $catalog->all(),
        );
        $userChoice = array_fill_keys($keys, true);

        foreach (NotificationTemplate::query()->whereIn('key', $keys)->get(['key', 'user_choice']) as $template) {
            $userChoice[$template->key] = (bool) $template->user_choice;
        }

        return view($theme->view('account.notification-settings'), [
            'theme' => $theme,
            'definitions' => $catalog->all(),
            'pushConfigured' => $vapidConfig !== null,
            'pushPublicKey' => $vapidConfig['publicKey'] ?? null,
            'userChoice' => $userChoice,
            'accountSection' => 'notification-settings',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.notifications.settings_title'),
            'theme' => $theme,
        ]);
    }
}

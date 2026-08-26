<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;

final class NotificationChannelPolicy
{
    public function allows(string $key, string $channel, ?User $user = null): bool
    {
        $template = NotificationTemplate::query()->where('key', $key)->first();
        if (! $template instanceof NotificationTemplate) {
            if (! $user instanceof User) {
                return true;
            }

            $preference = $user->notificationPreferences()->where('key', $key)->first();
            if ($preference === null) {
                return true;
            }

            return match ($channel) {
                'mail' => $preference->mail_enabled,
                'in_app' => $preference->in_app_enabled,
                'push' => $preference->push_enabled,
                default => false,
            };
        }

        $globallyEnabled = match ($channel) {
            'mail' => $template->enabled && $template->mail_enabled,
            'in_app' => $template->in_app_enabled,
            'push' => $template->push_enabled,
            default => false,
        };

        if (! $globallyEnabled) {
            return false;
        }

        if (! $template->user_choice || ! $user instanceof User) {
            return true;
        }

        $preference = $user->notificationPreferences()->where('key', $key)->first();
        if ($preference === null) {
            return true;
        }

        return match ($channel) {
            'mail' => $preference->mail_enabled,
            'in_app' => $preference->in_app_enabled,
            'push' => $preference->push_enabled,
            default => false,
        };
    }

    public function template(string $key): ?NotificationTemplate
    {
        $template = NotificationTemplate::query()->where('key', $key)->first();

        return $template instanceof NotificationTemplate ? $template : null;
    }
}

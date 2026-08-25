<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\SendPushNotification;

final class NotificationCenter
{
    public function notify(
        User $user,
        string $key,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void {
        $preference = $user->notificationPreferences()->where('key', $key)->first();
        $inAppEnabled = $preference === null || $preference->in_app_enabled;
        $pushEnabled = $preference === null || $preference->push_enabled;

        if ($inAppEnabled) {
            UserNotification::query()->create([
                'user_id' => $user->id,
                'key' => $key,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
            ]);
        }

        if (! $pushEnabled) {
            return;
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $actionUrl,
            'key' => $key,
        ];

        $user->pushSubscriptions()->each(function ($subscription) use ($payload): void {
            SendPushNotification::dispatch($subscription->id, $payload);
        });
    }
}

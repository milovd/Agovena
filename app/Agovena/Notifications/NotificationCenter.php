<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\SendPushNotification;

final class NotificationCenter
{
    public function __construct(
        private readonly NotificationChannelPolicy $policy,
        private readonly NotificationTemplateContent $content,
    ) {}

    public function notify(
        User $user,
        string $key,
        string $title,
        string $body,
        ?string $actionUrl = null,
        /** @var array<string, scalar|null> $vars */
        array $vars = [],
    ): void {
        $template = $this->policy->template($key);
        $templateVars = array_merge($vars, [
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
        $title = $this->content->render($template?->notification_title, $title, $templateVars);
        $body = $this->content->render($template?->notification_body, $body, $templateVars);

        if ($this->policy->allows($key, 'in_app', $user)) {
            UserNotification::query()->create([
                'user_id' => $user->id,
                'key' => $key,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
            ]);
        }

        if (! $this->policy->allows($key, 'push', $user)) {
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

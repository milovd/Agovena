<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Throwable;

final class LogFailedNotification
{
    public function handle(NotificationFailed $event): void
    {
        try {
            $to = $this->recipient($event);
            if ($to === '') {
                return;
            }

            $error = null;
            if (($event->data['exception'] ?? null) instanceof Throwable) {
                $error = mb_substr($event->data['exception']->getMessage(), 0, 2000);
            } elseif (is_string($event->data['message'] ?? null)) {
                $error = mb_substr((string) $event->data['message'], 0, 2000);
            }

            EmailLog::query()->create([
                'to' => $to,
                'subject' => null,
                'notification_key' => class_basename($event->notification),
                'status' => 'failed',
                'error' => $error,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Logging must never break checkout or queue workers.
        }
    }

    private function recipient(NotificationFailed $event): string
    {
        $notifiable = $event->notifiable;
        if ($notifiable instanceof AnonymousNotifiable) {
            $route = $notifiable->routeNotificationFor('mail');

            return is_string($route) ? $route : '';
        }

        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail', $event->notification);

            return is_string($route) ? $route : '';
        }

        return '';
    }
}

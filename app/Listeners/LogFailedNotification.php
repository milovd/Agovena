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

            EmailLog::query()->create([
                'to' => $to,
                'subject' => null,
                'notification_key' => class_basename($event->notification),
                'status' => 'failed',
                // Transport exceptions can contain credentials or customer data.
                'error' => 'Mail delivery failed.',
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

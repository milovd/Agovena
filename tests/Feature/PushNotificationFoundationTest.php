<?php

declare(strict_types=1);

use App\Agovena\Notifications\NotificationCenter;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\SendPushNotification;
use Illuminate\Support\Facades\Queue;

it('creates an in-app notification and queues push delivery for subscriptions', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    PushSubscription::query()->create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example.test/subscription',
        'p256dh_key' => '[REDACTED]',
        'auth_key' => '[REDACTED]',
    ]);

    app(NotificationCenter::class)->notify(
        $user,
        'payment_recorded',
        'Payment received',
        'Your payment was recorded.',
        'https://agovena.example.test/orders/1',
    );

    expect(UserNotification::query()->where('user_id', $user->id)->count())->toBe(1);
    Queue::assertPushed(SendPushNotification::class, 1);
});

it('respects a disabled push preference while keeping in-app delivery', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    PushSubscription::query()->create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example.test/subscription',
        'p256dh_key' => '[REDACTED]',
        'auth_key' => '[REDACTED]',
    ]);
    $user->notificationPreferences()->create([
        'key' => 'payment_recorded',
        'push_enabled' => false,
        'in_app_enabled' => true,
        'mail_enabled' => true,
    ]);

    app(NotificationCenter::class)->notify($user, 'payment_recorded', 'Payment received', 'Recorded.');

    expect(UserNotification::query()->where('user_id', $user->id)->count())->toBe(1);
    Queue::assertNotPushed(SendPushNotification::class);
});

<?php

declare(strict_types=1);

use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('runs a configured database backup through the artisan command', function (): void {
    Storage::fake('local');
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', database_path('database.sqlite'));

    expect(Artisan::call('agovena:backup'))->toBe(0);
    expect(Storage::disk('local')->files('backups'))->not->toBeEmpty();
});

it('alerts the configured operator when a backup fails without exposing diagnostics', function (): void {
    $originalDefault = config('database.default');
    $originalSqliteDatabase = config('database.connections.sqlite.database');

    try {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', storage_path('framework/missing-alert-backup.sqlite'));
        config()->set('agovena.backups.alert_email', 'ops@example.test');
        Notification::fake();

        expect(Artisan::call('agovena:backup'))->toBe(1);
        Notification::assertSentOnDemand(BackupFailedNotification::class, function (object $notification, array $channels, object $notifiable): bool {
            return in_array('mail', $channels, true)
                && $notification->errorCode === 'source_missing'
                && ($notifiable->routes['mail'] ?? null) === 'ops@example.test';
        });
    } finally {
        config()->set('database.default', $originalDefault);
        config()->set('database.connections.sqlite.database', $originalSqliteDatabase);
    }
});

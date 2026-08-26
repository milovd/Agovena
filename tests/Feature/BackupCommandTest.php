<?php

declare(strict_types=1);

use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('runs a configured database backup through the artisan command', function (): void {
    $source = storage_path('framework/command-backup.sqlite');
    file_put_contents($source, 'command backup payload');
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.driver', 'sqlite');
    config()->set('database.connections.sqlite.database', $source);
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'command-backups');
    Storage::fake('local');

    $exitCode = Artisan::call('agovena:backup');

    expect($exitCode)->toBe(0)
        ->and(Storage::disk('local')->files('command-backups'))->toHaveCount(1)
        ->and(Artisan::output())->toContain('Backup created:');

    unlink($source);
});

it('alerts the configured operator when a backup fails without exposing diagnostics', function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', storage_path('framework/missing-alert-backup.sqlite'));
    config()->set('agovena.backups.alert_email', 'ops@example.test');
    Notification::fake();

    expect(Artisan::call('agovena:backup'))->toBe(1);
    Notification::assertSentOnDemand(BackupFailedNotification::class, function (object $notification, array $channels, object $notifiable): bool {
        return $notifiable->routes['mail'] === 'ops@example.test'
            && $notification->errorCode === 'source_missing';
    });
});

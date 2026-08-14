<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Operations\SchedulerHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('doctor warns about failed jobs without failing required checks', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'simulated failure',
        'failed_at' => now(),
    ]);

    $this->artisan('agovena:doctor')
        ->expectsOutputToContain(__('installer.checks.failed_jobs'))
        ->assertSuccessful();
});

test('doctor fails in production when the private disk would be publicly served', function () {
    $previous = [
        'app.env' => config('app.env'),
        'app.debug' => config('app.debug'),
        'app.url' => config('app.url'),
        'filesystems.disks.local.serve' => config('filesystems.disks.local.serve'),
    ];

    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://shop.example.test',
        'filesystems.disks.local.serve' => true,
    ]);

    try {
        $this->artisan('agovena:doctor')->assertFailed();
    } finally {
        config($previous);
    }
});

test('scheduler is required when provisioning is enabled', function () {
    expect(app(SchedulerHealth::class)->isRequired())->toBeFalse();

    app(ModuleManager::class)->enable('provisioning');

    expect(app(SchedulerHealth::class)->isRequired())->toBeTrue()
        ->and(app(SchedulerHealth::class)->isStale())->toBeTrue();
});

test('doctor fails when a required scheduler is stale', function () {
    app(ModuleManager::class)->enable('provisioning');
    Cache::forget(SchedulerHealth::HEARTBEAT_KEY);

    $this->artisan('agovena:doctor')
        ->expectsOutputToContain(__('installer.checks.scheduler'))
        ->assertFailed();
});

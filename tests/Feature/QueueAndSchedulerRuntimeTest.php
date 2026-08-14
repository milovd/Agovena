<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('schedule run records a native heartbeat', function () {
    Cache::forget('agovena:scheduler:heartbeat');

    $this->artisan('schedule:run')->assertSuccessful();

    expect(Cache::get('agovena:scheduler:heartbeat'))->toBeString()->not->toBe('');
});

test('database queue worker consumes a dispatched job once', function () {
    config(['queue.default' => 'database']);
    Cache::put('agovena-queue-proof', 0);

    dispatch(function (): void {
        Cache::increment('agovena-queue-proof');
    });

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', [
        '--once' => true,
        '--tries' => 1,
    ])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and((int) Cache::get('agovena-queue-proof'))->toBe(1);
});

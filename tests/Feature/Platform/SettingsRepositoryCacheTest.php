<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Models\Setting;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** @return array{settings: int, writes: int} */
function settingsTraffic(Closure $read): array
{
    $settingsQueries = 0;
    $cacheWrites = 0;
    DB::listen(function ($query) use (&$settingsQueries): void {
        if (str_contains($query->sql, '"settings"')) {
            $settingsQueries++;
        }
    });
    Event::listen(KeyWritten::class, function () use (&$cacheWrites): void {
        $cacheWrites++;
    });

    $read();

    return ['settings' => $settingsQueries, 'writes' => $cacheWrites];
}

test('a missing setting is looked up once instead of on every read', function (): void {
    $settings = app(SettingsRepository::class);

    expect($settings->get('qa', 'never_saved', 'fallback'))->toBe('fallback');

    $traffic = settingsTraffic(function () use ($settings): void {
        expect($settings->get('qa', 'never_saved', 'fallback'))->toBe('fallback')
            ->and($settings->get('qa', 'never_saved'))->toBeNull();
    });

    expect($traffic)->toBe(['settings' => 0, 'writes' => 0]);
});

test('saving a setting that was cached as missing is visible on the next read', function (): void {
    $settings = app(SettingsRepository::class);

    expect($settings->get('qa', 'later', 'fallback'))->toBe('fallback');

    $settings->set('qa', 'later', 'saved');

    expect($settings->get('qa', 'later', 'fallback'))->toBe('saved');
});

test('a setting saved as empty stays distinct from a missing setting', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('qa', 'cleared', null);

    expect($settings->get('qa', 'cleared', 'fallback'))->toBeNull();

    $traffic = settingsTraffic(fn () => expect($settings->get('qa', 'cleared', 'fallback'))->toBeNull());

    expect($traffic)->toBe(['settings' => 0, 'writes' => 0]);
});

test('existing cached plain values keep working and forget picks up direct database changes', function (): void {
    $settings = app(SettingsRepository::class);
    Cache::forever('agovena.settings.qa.legacy', 'cached');

    expect($settings->get('qa', 'legacy'))->toBe('cached');

    expect($settings->get('qa', 'direct', 'fallback'))->toBe('fallback');
    Setting::query()->create(['group' => 'qa', 'key' => 'direct', 'value' => 'from-database']);
    $settings->forget('qa', 'direct');

    expect($settings->get('qa', 'direct', 'fallback'))->toBe('from-database');
});

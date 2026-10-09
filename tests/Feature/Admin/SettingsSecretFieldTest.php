<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Settings\Hub;
use App\Models\Setting;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function storedSettingValue(string $group, string $key): ?string
{
    return Setting::query()->where('group', $group)->where('key', $key)->value('value');
}

test('saving a password setting stores it encrypted', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class, ['tab' => 'store'])
        ->set('values.google_places_api_key', 'places-secret-123')
        ->call('save')
        ->assertHasNoErrors();

    expect(storedSettingValue('store', 'google_places_api_key'))->not->toContain('places-secret-123')
        ->and(app(SettingsRepository::class)->getSecret('store', 'google_places_api_key'))->toBe('places-secret-123');
});

test('the settings form never sends a stored secret to the browser', function () {
    app(SettingsRepository::class)->setSecret('store', 'google_places_api_key', 'places-secret-123');
    $staff = $this->createStaff();

    $component = Livewire::actingAs($staff)->test(Hub::class, ['tab' => 'store']);

    expect($component->get('values.google_places_api_key'))->toBe('')
        ->and($component->get('configuredSecrets.google_places_api_key'))->toBeTrue()
        ->and($component->html())->not->toContain('places-secret-123')
        ->and($component->html())->toContain(__('admin.settings.secret_configured'))
        ->and(json_encode($component->snapshot))->not->toContain('places-secret-123');
});

test('saving with an empty password field keeps the stored secret', function () {
    app(SettingsRepository::class)->setSecret('store', 'google_places_api_key', 'places-secret-123');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class, ['tab' => 'store'])
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->getSecret('store', 'google_places_api_key'))->toBe('places-secret-123');
});

test('a stored secret can be cleared explicitly', function () {
    app(SettingsRepository::class)->setSecret('store', 'google_places_api_key', 'places-secret-123');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class, ['tab' => 'store'])
        ->set('clearSecrets.google_places_api_key', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->getSecret('store', 'google_places_api_key'))->toBeNull();
});

test('secrets saved as plain text before encryption are still readable', function () {
    app(SettingsRepository::class)->set('store', 'google_places_api_key', 'legacy-plain-key');

    expect(app(SettingsRepository::class)->getSecret('store', 'google_places_api_key'))->toBe('legacy-plain-key');
});

test('the migration encrypts plain text password settings', function () {
    app(SettingsRepository::class)->set('store', 'google_places_api_key', 'legacy-plain-key');

    $migration = require database_path('migrations/2026_10_09_120000_encrypt_password_settings.php');
    $migration->up();
    app(SettingsRepository::class)->forget('store', 'google_places_api_key');

    expect(storedSettingValue('store', 'google_places_api_key'))->not->toContain('legacy-plain-key')
        ->and(app(SettingsRepository::class)->getSecret('store', 'google_places_api_key'))->toBe('legacy-plain-key');
});

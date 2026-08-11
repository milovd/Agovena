<?php

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Currencies\Index;
use App\Livewire\Admin\Settings\EditGroup;
use App\Models\Currency;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('branding logo setting survives cache round-trip as a scalar path', function () {
    $staff = $this->createStaff();
    $repo = app(SettingsRepository::class);

    $repo->set('branding', 'logo_path', 'branding/round-trip.png');

    expect($repo->get('branding', 'logo_path'))->toBe('branding/round-trip.png')
        ->and(Cache::get('agovena.settings.branding.logo_path'))->toBe('branding/round-trip.png')
        ->and(Cache::get('agovena.settings.branding.logo_path'))->toBeString()
        ->and(is_object(Cache::get('agovena.settings.branding.logo_path')))->toBeFalse();

    // Second read must use cache and still return a valid scalar (not Incomplete_Class).
    expect($repo->get('branding', 'logo_path'))->toBe('branding/round-trip.png');

    $this->actingAs($staff, 'staff')
        ->get(route('admin.settings.edit', ['group' => 'branding']))
        ->assertOk()
        ->assertSee('Branding', false);
});

test('settings repository recovers from stale model cache entries', function () {
    $repo = app(SettingsRepository::class);
    $repo->set('branding', 'logo_path', 'branding/demo.png');

    Cache::put('agovena.settings.branding.logo_path', (object) ['broken' => true], 3600);

    expect($repo->get('branding', 'logo_path'))->toBe('branding/demo.png')
        ->and(is_object(Cache::get('agovena.settings.branding.logo_path')))->toBeFalse();
});

test('money formatter uses currency prefix suffix and precision without floats', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'SEK'],
        ['name' => 'Swedish Krona', 'prefix' => '', 'suffix' => ' kr', 'precision' => 2, 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'JPY'],
        ['name' => 'Japanese Yen', 'prefix' => '¥', 'suffix' => '', 'precision' => 0, 'is_active' => true],
    );
    app(CurrencyCatalog::class)->forget('SEK');
    app(CurrencyCatalog::class)->forget('JPY');

    expect(MoneyFormatter::format(1999, 'SEK'))->toBe('19.99 kr')
        ->and(MoneyFormatter::format(1234, 'JPY'))->toBe('¥1,234');
});

test('owner can create a currency with prefix suffix and precision', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff, 'staff')
        ->test(Index::class)
        ->call('create')
        ->set('code', 'nok')
        ->set('name', 'Norwegian Krone')
        ->set('prefix', '')
        ->set('suffix', ' kr')
        ->set('precision', 2)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('currencies', [
        'code' => 'NOK',
        'suffix' => ' kr',
        'precision' => 2,
    ]);
});

test('owner can set base currency from currencies admin', function () {
    $staff = $this->createStaff();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    Livewire::actingAs($staff, 'staff')
        ->test(Index::class)
        ->call('setAsBase', $usd->id)
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('general', 'base_currency'))->toBe('USD');
});

test('branding page can set favicon from logo path without merging settings keys', function () {
    $staff = $this->createStaff();
    $settings = app(SettingsRepository::class);
    $settings->set('branding', 'logo_path', 'branding/logo.png');
    $settings->set('branding', 'favicon_path', 'branding/old-favicon.png');

    Livewire::actingAs($staff, 'staff')
        ->test(EditGroup::class, ['group' => 'branding'])
        ->call('useCurrentLogoAsFavicon')
        ->assertHasNoErrors();

    expect($settings->get('branding', 'logo_path'))->toBe('branding/logo.png')
        ->and($settings->get('branding', 'favicon_path'))->toBe('branding/logo.png');
});

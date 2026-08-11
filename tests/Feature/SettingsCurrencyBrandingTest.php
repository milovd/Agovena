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

test('settings repository recovers from stale model cache entries', function () {
    $repo = app(SettingsRepository::class);
    $repo->set('branding', 'logo_path', 'branding/demo.png');

    Cache::put('agovena.settings.branding.logo_path', (object) ['broken' => true], 3600);

    expect($repo->get('branding', 'logo_path'))->toBe('branding/demo.png');
});

test('money formatter uses currency prefix and suffix', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'SEK'],
        ['name' => 'Swedish Krona', 'prefix' => '', 'suffix' => ' kr', 'is_active' => true],
    );
    app(CurrencyCatalog::class)->forget('SEK');

    expect(MoneyFormatter::format(1999, 'SEK'))->toBe('19.99 kr');
});

test('owner can create a currency with prefix and suffix', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff, 'staff')
        ->test(Index::class)
        ->call('create')
        ->set('code', 'nok')
        ->set('name', 'Norwegian Krone')
        ->set('prefix', '')
        ->set('suffix', ' kr')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('currencies', [
        'code' => 'NOK',
        'suffix' => ' kr',
    ]);
});

test('branding page can set favicon from logo path', function () {
    $staff = $this->createStaff();
    $settings = app(SettingsRepository::class);
    $settings->set('branding', 'logo_path', 'branding/logo.png');

    Livewire::actingAs($staff, 'staff')
        ->test(EditGroup::class, ['group' => 'branding'])
        ->call('useCurrentLogoAsFavicon')
        ->assertHasNoErrors();

    expect($settings->get('branding', 'favicon_path'))->toBe('branding/logo.png');
});

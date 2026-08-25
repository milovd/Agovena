<?php

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Currencies\Index;
use App\Livewire\Admin\Settings\Hub;
use App\Models\Currency;
use App\Support\MoneyFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    $this->actingAs($staff)
        ->get(route('admin.settings.edit', ['group' => 'branding']))
        ->assertRedirect(route('admin.settings.index', ['tab' => 'branding']));

    $this->actingAs($staff)
        ->get(route('admin.settings.index', ['tab' => 'branding']))
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

    Livewire::actingAs($staff)
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

    Livewire::actingAs($staff)
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

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'branding')
        ->call('useCurrentLogoAsFavicon')
        ->assertHasNoErrors();

    expect($settings->get('branding', 'logo_path'))->toBe('branding/logo.png')
        ->and($settings->get('branding', 'favicon_path'))->toBe('branding/logo.png');
});

test('branding uploads reject svg files', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'branding')
        ->set('uploads.logo_path', UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'))
        ->call('save')
        ->assertHasErrors(['uploads.logo_path']);
});

test('currency sync updates rates from frankfurter market api', function () {
    $staff = $this->createStaff();
    Currency::query()->updateOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'prefix' => '€', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    app(SettingsRepository::class)->set('general', 'base_currency', 'EUR');

    Http::fake([
        'api.frankfurter.dev/*' => Http::response([
            'amount' => 1,
            'base' => 'EUR',
            'date' => '2026-08-25',
            'rates' => ['USD' => 1.12345678],
        ], 200),
    ]);

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('syncRates')
        ->assertHasNoErrors()
        ->assertSee('Updated 2 rate(s) from Frankfurter', false);

    expect(Currency::query()->where('code', 'USD')->value('exchange_rate'))->toBe('1.12345678')
        ->and(Currency::query()->where('code', 'EUR')->value('exchange_rate'))->toBe('1.00000000');

    Http::assertSent(function ($request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), 'api.frankfurter.dev/v1/latest')
            && ($query['base'] ?? null) === 'EUR'
            && str_contains((string) ($query['symbols'] ?? ''), 'USD');
    });
});

test('currency sync surfaces frankfurter http failures', function () {
    $staff = $this->createStaff();
    Currency::query()->updateOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'prefix' => '€', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    app(SettingsRepository::class)->set('general', 'base_currency', 'EUR');

    Http::fake([
        'api.frankfurter.dev/*' => Http::response('upstream unavailable', 503),
    ]);

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('syncRates')
        ->assertSee('Could not sync exchange rates', false)
        ->assertSee('HTTP 503', false);

    expect(Currency::query()->where('code', 'USD')->value('exchange_rate'))->toBe('1.00000000');
});

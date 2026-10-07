<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Support\Facades\Session;

test('visitor can switch storefront locale via preferences', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'en');

    $this->from('/')
        ->post(route('storefront.preferences.locale'), ['locale' => 'nl'])
        ->assertRedirect('/');

    expect(Session::get(StorefrontPreferences::SESSION_LOCALE))->toBe('nl');

    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.cart', [], 'nl'), false)
        ->assertSee('data-theme=', false);
});

test('visitor can switch storefront currency among active currencies', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'is_active' => true],
    );

    $this->from('/')
        ->post(route('storefront.preferences.currency'), ['currency' => 'USD'])
        ->assertRedirect('/');

    expect(app(StorefrontPreferences::class)->currencyCode())->toBe('USD');

    $this->get('/')
        ->assertOk()
        ->assertSee('USD', false)
        ->assertSee(__('storefront.preferences.currency'), false);
});

test('header exposes combined region control and theme toggle', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'is_active' => true],
    );

    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.preferences.theme_to_dark'), false)
        ->assertSee(__('storefront.preferences.region'), false)
        ->assertSee(__('storefront.preferences.language'), false)
        ->assertSee(__('storefront.preferences.currency'), false)
        ->assertSee('store-header__region-trigger', false)
        ->assertSee('ag-flag', false)
        ->assertSee(route('storefront.preferences.locale'), false)
        ->assertSee(route('storefront.preferences.currency'), false);
});

test('catalog shows products across currencies when preference is set', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.08000000', 'is_active' => true],
    );

    $eur = Product::factory()->active()->create(['name' => 'Euro Gadget', 'currency' => 'EUR']);
    $usd = Product::factory()->active()->create(['name' => 'Dollar Gadget', 'currency' => 'USD']);

    app(StorefrontPreferences::class)->setCurrency('USD');

    $this->get('/')
        ->assertOk()
        ->assertSee('Dollar Gadget', false)
        ->assertSee('Euro Gadget', false);

    expect($eur->currency)->toBe('EUR')
        ->and($usd->currency)->toBe('USD');
});

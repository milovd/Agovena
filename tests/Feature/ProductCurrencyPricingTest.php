<?php

declare(strict_types=1);

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\ResolveProductPrice;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCurrencyPrice;
use App\Support\MoneyFormatter;

beforeEach(function (): void {
    app(SettingsRepository::class)->set('general', 'base_currency', 'EUR');
    app(SettingsRepository::class)->set('general', 'auto_currency_conversion', true);

    Currency::query()->updateOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'prefix' => '€', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '2.00000000', 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'GBP'],
        ['name' => 'Pound Sterling', 'prefix' => '£', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '0.50000000', 'is_active' => true],
    );

    foreach (['EUR', 'USD', 'GBP'] as $code) {
        app(CurrencyCatalog::class)->forget($code);
    }
});

test('auto conversion converts product prices via exchange rates', function () {
    $product = Product::factory()->active()->create([
        'name' => 'Euro Only',
        'currency' => 'EUR',
        'price_amount' => 1000,
    ]);

    app(StorefrontPreferences::class)->setCurrency('USD');

    $resolved = app(ResolveProductPrice::class)->resolve($product);
    expect($resolved)->not->toBeNull()
        ->and($resolved->source)->toBe(ResolveProductPrice::SOURCE_CONVERTED)
        ->and($resolved->money->amount)->toBe(2000)
        ->and(MoneyFormatter::formatProduct($product))->toBe('$20.00');
});

test('manual currency price overrides conversion', function () {
    $product = Product::factory()->active()->create([
        'currency' => 'EUR',
        'price_amount' => 1000,
    ]);
    ProductCurrencyPrice::query()->create([
        'product_id' => $product->id,
        'currency' => 'USD',
        'price_amount' => 1550,
    ]);
    $product->load('currencyPrices');

    app(StorefrontPreferences::class)->setCurrency('USD');

    $resolved = app(ResolveProductPrice::class)->resolve($product);
    expect($resolved)->not->toBeNull()
        ->and($resolved->source)->toBe(ResolveProductPrice::SOURCE_MANUAL)
        ->and($resolved->money->amount)->toBe(1550)
        ->and(MoneyFormatter::formatProduct($product))->toBe('$15.50');
});

test('disabled auto conversion marks products unavailable without manual price', function () {
    app(SettingsRepository::class)->set('general', 'auto_currency_conversion', false);

    $product = Product::factory()->active()->create([
        'name' => 'Euro Locked',
        'currency' => 'EUR',
        'price_amount' => 1000,
    ]);

    app(StorefrontPreferences::class)->setCurrency('GBP');

    expect(app(ResolveProductPrice::class)->resolve($product))->toBeNull()
        ->and(MoneyFormatter::formatProduct($product))->toBeNull();

    $this->get('/')
        ->assertOk()
        ->assertSee('Euro Locked', false)
        ->assertSee(__('storefront.product.not_available_in_currency'), false);
});

test('disabled auto conversion still sells when manual price exists', function () {
    app(SettingsRepository::class)->set('general', 'auto_currency_conversion', false);

    $product = Product::factory()->active()->create([
        'currency' => 'EUR',
        'price_amount' => 1000,
    ]);
    ProductCurrencyPrice::query()->create([
        'product_id' => $product->id,
        'currency' => 'GBP',
        'price_amount' => 900,
    ]);
    $product->load('currencyPrices');

    app(StorefrontPreferences::class)->setCurrency('GBP');

    $resolved = app(ResolveProductPrice::class)->resolve($product);
    expect($resolved)->not->toBeNull()
        ->and($resolved->source)->toBe(ResolveProductPrice::SOURCE_MANUAL)
        ->and($resolved->money->amount)->toBe(900);
});

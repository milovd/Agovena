<?php

declare(strict_types=1);

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Money\CurrencyConverter;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Models\Currency;
use App\Models\Product;
use App\Support\MoneyFormatter;

beforeEach(function (): void {
    app(SettingsRepository::class)->set('general', 'base_currency', 'EUR');

    Currency::query()->updateOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'prefix' => '€', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'prefix' => '$', 'suffix' => '', 'precision' => 2, 'exchange_rate' => '2.00000000', 'is_active' => true],
    );

    app(CurrencyCatalog::class)->forget('EUR');
    app(CurrencyCatalog::class)->forget('USD');
});

test('currency converter converts minor units via exchange rates without floats', function () {
    $converter = app(CurrencyConverter::class);

    // €10.00 at 1 EUR = 2 USD => $20.00
    expect($converter->convert(1000, 'EUR', 'USD'))->toBe(2000)
        ->and($converter->convert(2000, 'USD', 'EUR'))->toBe(1000)
        ->and($converter->convert(1000, 'EUR', 'EUR'))->toBe(1000);
});

test('money formatter display uses preferred storefront currency', function () {
    app(StorefrontPreferences::class)->setCurrency('USD');

    expect(MoneyFormatter::formatDisplay(1000, 'EUR'))->toBe('$20.00');
});

test('catalog keeps products from other currencies and shows converted prices', function () {
    Product::factory()->active()->create([
        'name' => 'Euro Gadget',
        'currency' => 'EUR',
        'price_amount' => 1000,
    ]);
    Product::factory()->active()->create([
        'name' => 'Dollar Gadget',
        'currency' => 'USD',
        'price_amount' => 500,
    ]);

    app(StorefrontPreferences::class)->setCurrency('USD');

    $this->get('/')
        ->assertOk()
        ->assertSee('Euro Gadget', false)
        ->assertSee('Dollar Gadget', false)
        ->assertSee('$20.00', false)
        ->assertSee('$5.00', false);
});

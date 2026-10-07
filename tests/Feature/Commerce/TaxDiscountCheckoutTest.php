<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\AutomaticTaxRateProvider;
use App\Agovena\Tax\TaxRateResolver;
use App\Agovena\Tax\TaxRateSource;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    // Suite defaults automatic tax off; re-enable for tax behavior coverage.
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', true);
});

function addTaxDiscountProduct(int $amount): void
{
    $product = Product::factory()->active()->create([
        'price_amount' => $amount,
        'currency' => 'EUR',
    ]);
    app(CartService::class)->add($product->id);
}

function taxDiscountAddress(string $country = 'NL'): AddressData
{
    return new AddressData(
        name: 'Tax Buyer',
        line1: 'Street 1',
        city: 'City',
        postalCode: '1000AA',
        country: $country,
    );
}

function placeTaxDiscountOrder(array $overrides = [])
{
    return app(PlaceOrder::class)->handle([
        'customer_name' => 'Tax Buyer',
        'customer_email' => 'tax@example.com',
        'billing' => taxDiscountAddress('US'),
        ...$overrides,
    ]);
}

test('exclusive tax adds tax to total from automatic rates', function () {
    addTaxDiscountProduct(10000);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('NL')]);

    expect($order->subtotal_amount)->toBe(10000)
        ->and($order->tax_amount)->toBe(2100)
        ->and($order->total_amount)->toBe(12100)
        ->and($order->tax_rate_bps)->toBe(2100)
        ->and($order->tax_rate_name)->toBe('NL VAT');
});

test('merchant override wins over automatic rate', function () {
    addTaxDiscountProduct(10000);
    TaxRate::query()->create([
        'name' => 'DE custom',
        'rate_bps' => 1000,
        'country' => 'DE',
    ]);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('DE')]);

    expect($order->tax_amount)->toBe(1000)
        ->and($order->tax_rate_bps)->toBe(1000)
        ->and($order->tax_rate_name)->toBe('DE custom')
        ->and($order->total_amount)->toBe(11000);
});

test('disabled country skips automatic tax', function () {
    addTaxDiscountProduct(10000);
    TaxRate::query()->create([
        'name' => 'DE disabled',
        'rate_bps' => 0,
        'country' => 'DE',
        'is_disabled' => true,
    ]);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('DE')]);

    expect($order->tax_amount)->toBe(0)
        ->and($order->tax_rate_bps)->toBeNull()
        ->and($order->total_amount)->toBe(10000);
});

test('automatic rates apply german standard rate without tax rate row', function () {
    addTaxDiscountProduct(10000);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('DE')]);

    expect($order->tax_amount)->toBe(1900)
        ->and($order->tax_rate_bps)->toBe(1900)
        ->and($order->total_amount)->toBe(11900);
});

test('tax master off ignores automatic rates and overrides', function () {
    app(SettingsRepository::class)->set('store', 'tax_enabled', false);
    addTaxDiscountProduct(10000);
    TaxRate::query()->create([
        'name' => 'NL custom',
        'rate_bps' => 1000,
        'country' => 'NL',
    ]);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('NL')]);

    expect($order->tax_amount)->toBe(0)
        ->and($order->tax_rate_bps)->toBeNull()
        ->and($order->total_amount)->toBe(10000);
});

test('automatic off uses only manual rates', function () {
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', false);
    addTaxDiscountProduct(10000);

    $orderWithoutRate = placeTaxDiscountOrder(['billing' => taxDiscountAddress('NL')]);
    expect($orderWithoutRate->tax_amount)->toBe(0)
        ->and($orderWithoutRate->total_amount)->toBe(10000);

    TaxRate::query()->create([
        'name' => 'NL manual',
        'rate_bps' => 900,
        'country' => 'NL',
    ]);

    addTaxDiscountProduct(10000);
    $orderWithRate = placeTaxDiscountOrder(['billing' => taxDiscountAddress('NL')]);
    expect($orderWithRate->tax_amount)->toBe(900)
        ->and($orderWithRate->tax_rate_bps)->toBe(900)
        ->and($orderWithRate->total_amount)->toBe(10900);
});

test('inclusive tax extracts tax without increasing total', function () {
    app(SettingsRepository::class)->set('store', 'prices_include_tax', true);
    addTaxDiscountProduct(12100);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('NL')]);

    expect($order->tax_amount)->toBe(2100)
        ->and($order->total_amount)->toBe(12100);
});

test('null-country fallback applies when automatic rates are off', function () {
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', false);
    addTaxDiscountProduct(10000);
    TaxRate::query()->create([
        'name' => 'Global',
        'rate_bps' => 500,
        'country' => null,
    ]);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('US')]);

    expect($order->tax_amount)->toBe(500)
        ->and($order->tax_rate_bps)->toBe(500)
        ->and($order->total_amount)->toBe(10500);
});

test('automatic on does not use null-country fallback', function () {
    addTaxDiscountProduct(10000);
    TaxRate::query()->create([
        'name' => 'Global',
        'rate_bps' => 500,
        'country' => null,
    ]);

    $order = placeTaxDiscountOrder(['billing' => taxDiscountAddress('US')]);

    expect($order->tax_amount)->toBe(0)
        ->and($order->tax_rate_bps)->toBeNull()
        ->and($order->total_amount)->toBe(10000);
});

test('tax rate resolver reports automatic override and disabled sources', function () {
    $resolver = app(TaxRateResolver::class);

    expect($resolver->resolve('DE')->source)->toBe(TaxRateSource::Automatic)
        ->and($resolver->resolve('DE')->rateBps)->toBe(1900);

    TaxRate::query()->create(['name' => 'DE custom', 'rate_bps' => 700, 'country' => 'DE']);
    expect($resolver->resolve('DE')->source)->toBe(TaxRateSource::Override)
        ->and($resolver->resolve('DE')->rateBps)->toBe(700);

    TaxRate::query()->where('country', 'DE')->update(['is_disabled' => true, 'rate_bps' => 0]);
    expect($resolver->resolve('DE')->source)->toBe(TaxRateSource::Disabled)
        ->and($resolver->resolve('DE')->applies())->toBeFalse();

    expect(app(AutomaticTaxRateProvider::class)->version())->not->toBe('')
        ->and($resolver->merchantRates())->toHaveCount(1);
});

test('tax rate resolver respects master and automatic switches', function () {
    $settings = app(SettingsRepository::class);
    $resolver = app(TaxRateResolver::class);

    expect($resolver->taxEnabled())->toBeTrue()
        ->and($resolver->automaticTaxRates())->toBeTrue()
        ->and($resolver->resolve('NL')->source)->toBe(TaxRateSource::Automatic);

    $settings->set('store', 'automatic_tax_rates', false);
    expect($resolver->resolve('NL')->source)->toBe(TaxRateSource::None);

    $settings->set('store', 'tax_enabled', false);
    $settings->set('store', 'automatic_tax_rates', true);
    expect($resolver->resolve('NL')->source)->toBe(TaxRateSource::None);
});

test('percent coupon reduces total', function () {
    addTaxDiscountProduct(10000);
    DiscountCode::query()->create([
        'code' => 'SAVE10',
        'type' => 'percent',
        'value' => 10,
    ]);

    $order = placeTaxDiscountOrder(['discount_code' => 'save10']);

    expect($order->discount_amount)->toBe(1000)
        ->and($order->discount_code)->toBe('SAVE10')
        ->and($order->tax_amount)->toBe(0)
        ->and($order->total_amount)->toBe(9000);
});

test('invalid and expired coupons fail', function () {
    addTaxDiscountProduct(1000);

    expect(fn () => placeTaxDiscountOrder(['discount_code' => 'NOPE']))
        ->toThrow(ValidationException::class);

    DiscountCode::query()->create([
        'code' => 'OLD',
        'type' => 'percent',
        'value' => 10,
        'ends_at' => now()->subMinute(),
    ]);

    expect(fn () => placeTaxDiscountOrder(['discount_code' => 'OLD']))
        ->toThrow(ValidationException::class);
});

test('redemption is recorded once for idempotent order placement', function () {
    addTaxDiscountProduct(1000);
    DiscountCode::query()->create([
        'code' => 'ONCE',
        'type' => 'fixed',
        'value' => 100,
        'currency' => 'EUR',
    ]);

    $first = placeTaxDiscountOrder([
        'discount_code' => 'ONCE',
        'idempotency_key' => 'discount-once',
    ]);
    $second = placeTaxDiscountOrder([
        'discount_code' => 'ONCE',
        'idempotency_key' => 'discount-once',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(DiscountRedemption::query()->where('order_id', $first->id)->count())->toBe(1);
});

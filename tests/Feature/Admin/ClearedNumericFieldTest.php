<?php

declare(strict_types=1);

use App\Livewire\Admin\Currencies\Index as CurrenciesIndex;
use App\Livewire\Admin\Discounts\Index as DiscountsIndex;
use App\Livewire\Admin\Taxes\Index as TaxesIndex;
use App\Models\Currency;
use App\Models\DiscountCode;
use App\Models\TaxRate;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * A cleared number input arrives as an empty string. A required business value must
 * then fail validation instead of silently falling back to the component default.
 */

test('clearing a discount value rejects the save instead of applying the default discount', function (): void {
    $discount = DiscountCode::query()->create([
        'code' => 'SAVE25',
        'type' => 'percent',
        'value' => 25,
        'currency' => null,
        'min_subtotal_amount' => 0,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(DiscountsIndex::class)
        ->call('edit', $discount->id)
        ->set('value', '')
        ->call('save')
        ->assertHasErrors(['value']);

    expect($discount->fresh()->value)->toBe(25);
});

test('clearing a tax rate rejects the save instead of applying the default rate', function (): void {
    $rate = TaxRate::query()->create([
        'name' => 'Reduced rate',
        'rate_bps' => 600,
        'country' => 'BE',
        'region' => null,
        'is_active' => true,
        'is_disabled' => false,
        'applies_to_shipping' => false,
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(TaxesIndex::class)
        ->call('edit', $rate->id)
        ->set('rate_bps', '')
        ->call('save')
        ->assertHasErrors(['rate_bps']);

    expect($rate->fresh()->rate_bps)->toBe(600);
});

test('clearing a currency precision rejects the save instead of applying the default precision', function (): void {
    $currency = Currency::query()->updateOrCreate(
        ['code' => 'JPY'],
        ['name' => 'Japanese yen', 'prefix' => '¥', 'suffix' => '', 'precision' => 0, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );

    Livewire::actingAs($this->createStaff())
        ->test(CurrenciesIndex::class)
        ->call('edit', $currency->id)
        ->set('precision', '')
        ->call('save')
        ->assertHasErrors(['precision']);

    expect($currency->fresh()->precision)->toBe(0);
});

test('new discount, tax rate and currency forms still start from their usual defaults', function (): void {
    $staff = $this->createStaff();

    expect(Livewire::actingAs($staff)->test(DiscountsIndex::class)->call('create')->get('value'))->toBe(10)
        ->and(Livewire::actingAs($staff)->test(TaxesIndex::class)->call('create')->get('rate_bps'))->toBe(2100)
        ->and(Livewire::actingAs($staff)->test(CurrenciesIndex::class)->call('create')->get('precision'))->toBe(2);
});

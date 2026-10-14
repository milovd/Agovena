<?php

declare(strict_types=1);

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Livewire\Admin\Orders\Index;
use App\Models\Currency;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('resolves display currency once instead of querying settings for every order row', function (): void {
    $staff = $this->createStaff();
    Order::factory()->count(15)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($staff)->test(Index::class)->assertOk();

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $settingsQueries = $queries->filter(static fn (array $query): bool => str_contains($query['query'], 'from "settings"')
        || str_contains($query['query'], 'from `settings`')
    )->count();

    expect($settingsQueries)->toBeLessThan(10);
});

it('keeps the same converted total in desktop and mobile order rows', function (): void {
    app(SettingsRepository::class)->set('general', 'base_currency', 'EUR');
    foreach (['EUR' => ['€', '1.00000000'], 'USD' => ['$', '2.00000000']] as $code => [$prefix, $rate]) {
        Currency::query()->updateOrCreate(['code' => $code], [
            'name' => $code, 'prefix' => $prefix, 'suffix' => '',
            'precision' => 2, 'exchange_rate' => $rate, 'is_active' => true,
        ]);
        app(CurrencyCatalog::class)->forget($code);
    }

    $staff = $this->createStaff();
    $order = Order::factory()->create(['total_amount' => 1000, 'currency' => 'EUR']);
    app(StorefrontPreferences::class)->setCurrency('USD');

    $html = Livewire::actingAs($staff)->test(Index::class)->assertSee($order->number)->html();
    expect(substr_count($html, '$20.00'))->toBe(2);
});

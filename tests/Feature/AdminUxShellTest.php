<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Dashboard;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin shell exposes view storefront and regrouped navigation', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.view_storefront'), false)
        ->assertSee(route('storefront.home'), false)
        ->assertSee(__('admin.nav_groups.system'), false)
        ->assertDontSee(__('admin.nav.customer_properties'), false);
});

test('dashboard renders real metrics without fake trends', function () {
    Livewire::actingAs($this->createStaff())
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('admin.dashboard.heading'), false)
        ->assertSee(__('admin.dashboard.stats.orders'), false)
        ->assertSee(__('admin.dashboard.charts.revenue'), false);
});

test('customer index shows identity and commerce columns', function () {
    $customer = Customer::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@agovena.test',
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(CustomersIndex::class)
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@agovena.test')
        ->assertSee(__('admin.customers.spent_column'), false)
        ->assertSee(__('admin.customers.status_active'), false);
});

test('disabled modules do not leave dead fulfillment navigation', function () {
    expect(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse();

    $ids = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');

    expect($ids)->not->toContain('inventory-stocks');
});

test('enabled subscriptions appear under operations navigation', function () {
    app(ModuleManager::class)->enable('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $item = collect(app(AdminRegistrar::class)->navigationItems())
        ->firstWhere('id', 'subscriptions');

    expect($item)->not->toBeNull()
        ->and($item->group)->toBe('admin.nav_groups.operations');
});

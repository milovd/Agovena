<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Customer;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * @param  list<string>  $ids
 */
function enableFirstPartyModules(array $ids): void
{
    $modules = app(ModuleManager::class);
    foreach ($ids as $id) {
        $modules->enable($id);
    }
    app(SyncRegisteredPermissions::class)(force: true);
}

test('core admin and account work with zero optional modules enabled', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->not->toContain('inventory-stocks')
        ->and($nav)->not->toContain('shipping-methods')
        ->and($nav)->not->toContain('digital-assets')
        ->and($nav)->not->toContain('subscriptions')
        ->and($nav)->not->toContain('provisioning')
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('SQLSTATE', false);

    $this->actingAs($staff)
        ->get(route('admin.products.create'))
        ->assertOk();

    $this->actingAs($staff)
        ->get(route('admin.customers.properties'))
        ->assertOk();

    $this->actingAs($customer->user)
        ->get(route('customer.account'))
        ->assertOk();

    $accountNav = collect(app(CustomerAccountNav::class)->items())->pluck('id');
    expect($accountNav)->not->toContain('digital-downloads')
        ->and($accountNav)->not->toContain('subscriptions')
        ->and($accountNav)->not->toContain('services');

    $this->actingAs($staff)->get('/admin/inventory')->assertNotFound();
    $this->actingAs($customer->user)->get('/account/downloads')->assertNotFound();
});

test('each first-party module admin screen renders when that module is enabled alone', function (string $moduleId, string $adminPath) {
    $staff = $this->createStaff();
    enableFirstPartyModules([$moduleId]);

    $this->actingAs($staff)
        ->get($adminPath)
        ->assertOk()
        ->assertDontSee('SQLSTATE', false)
        ->assertDontSee('Server Error', false);
})->with([
    'inventory' => ['inventory', '/admin/inventory'],
    'shipping' => ['shipping', '/admin/shipping/methods'],
    'digital' => ['digital', '/admin/digital/assets'],
    'subscriptions' => ['subscriptions', '/admin/subscriptions'],
    'provisioning' => ['provisioning', '/admin/provisioning'],
]);

test('all first-party modules together expose admin and account surfaces', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    enableFirstPartyModules(['inventory', 'shipping', 'digital', 'subscriptions', 'provisioning']);

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->toContain('inventory-stocks')
        ->and($nav)->toContain('digital-assets')
        ->and($nav)->toContain('subscriptions')
        ->and($nav)->toContain('provisioning');

    $shippingNames = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => is_string($name) && str_contains((string) $name, 'shipping'))
        ->values()
        ->all();
    expect($shippingNames)->toContain('admin.shipping.methods')
        ->and($shippingNames)->toContain('admin.shipping.zones');

    $this->actingAs($staff);
    foreach ([
        '/admin/inventory',
        '/admin/shipping/methods',
        '/admin/shipping/zones',
        '/admin/digital/assets',
        '/admin/subscriptions',
        '/admin/provisioning',
        '/admin/plan-changes',
    ] as $uri) {
        $this->get($uri)->assertOk()->assertDontSee('SQLSTATE', false);
    }

    $this->actingAs($customer->user);
    foreach ([
        '/account/downloads',
        '/account/subscriptions',
        '/account/services',
    ] as $uri) {
        $this->get($uri)->assertOk()->assertDontSee('SQLSTATE', false);
    }
});

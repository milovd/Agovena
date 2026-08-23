<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\AgovenaModule;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * @param  list<string>  $ids
 */
function enableFirstPartyModules(array $ids): void
{
    installAndEnableModules($ids);
    app(SyncRegisteredPermissions::class)(force: true);
}

test('module enable fails when module is not installed', function () {
    AgovenaModule::query()->where('module_id', 'inventory')->delete();

    expect(fn () => app(ModuleManager::class)->enable('inventory'))
        ->toThrow(ValidationException::class, 'Install Module inventory before enabling it.');
});

test('core admin and account work with zero optional modules enabled', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->not->toContain('inventory-stocks')
        ->and($nav)->not->toContain('shipping-methods')
        ->and($nav)->not->toContain('digital-assets')
        ->and($nav)->not->toContain('digital-delivery-secrets')
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
        ->and($accountNav)->not->toContain('digital-secrets')
        ->and($accountNav)->not->toContain('subscriptions')
        ->and($accountNav)->not->toContain('services');

    $this->actingAs($staff)->get('/admin/inventory')->assertNotFound();
    $this->actingAs($customer->user)->get('/account/downloads')->assertNotFound();
    $this->actingAs($customer->user)->get('/account/digital-secrets')->assertNotFound();
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
    'shipping-returns' => ['shipping', '/admin/shipping/returns'],
    'digital' => ['digital', '/admin/digital/assets'],
    'digital-delivery' => ['digital-delivery', '/admin/digital-delivery/secrets'],
    'subscriptions' => ['subscriptions', '/admin/subscriptions'],
    'provisioning' => ['provisioning', '/admin/provisioning'],
    'events' => ['events', '/admin/events'],
]);

test('events are configured inside products while check in remains an operations workflow', function () {
    enableFirstPartyModules(['events']);

    $items = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');
    $tabs = collect(app(AdminRegistrar::class)->productTabs())->keyBy('id');

    expect($items)->not->toHaveKey('events')
        ->and($tabs)->toHaveKey('events')
        ->and($items->get('events-checkin')?->group)->toBe('admin.nav_groups.operations')
        ->and($items->get('events-checkin')?->parent)->toBeNull()
        ->and($items->get('tickets')?->group)->toBe('admin.nav_groups.operations');
});

test('all first-party modules together expose admin and account surfaces', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    enableFirstPartyModules(['inventory', 'shipping', 'digital', 'digital-delivery', 'subscriptions', 'provisioning', 'events']);

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->toContain('inventory-stocks')
        ->and($nav)->toContain('digital-assets')
        ->and($nav)->toContain('digital-delivery-secrets')
        ->and($nav)->toContain('subscriptions')
        ->and($nav)->toContain('provisioning')
        ->and($nav)->not->toContain('events')
        ->and($nav)->toContain('events-checkin');

    expect(collect(app(AdminRegistrar::class)->productTabs())->pluck('id'))->toContain('events');

    $shippingNames = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => is_string($name) && str_contains((string) $name, 'shipping'))
        ->values()
        ->all();
    expect($shippingNames)->toContain('admin.shipping.methods')
        ->and($shippingNames)->toContain('admin.shipping.zones')
        ->and($shippingNames)->toContain('admin.shipping.returns');

    $this->actingAs($staff);
    foreach ([
        '/admin/inventory',
        '/admin/shipping/methods',
        '/admin/shipping/zones',
        '/admin/shipping/returns',
        '/admin/digital/assets',
        '/admin/digital-delivery/secrets',
        '/admin/subscriptions',
        '/admin/provisioning',
        '/admin/plan-changes',
        '/admin/events',
        '/admin/events/check-in',
    ] as $uri) {
        $this->get($uri)->assertOk()->assertDontSee('SQLSTATE', false);
    }

    $this->actingAs($customer->user);
    foreach ([
        '/account/downloads',
        '/account/digital-secrets',
        '/account/subscriptions',
        '/account/services',
        '/account/event-tickets',
        '/account/returns',
    ] as $uri) {
        $this->get($uri)->assertOk()->assertDontSee('SQLSTATE', false);
    }
});

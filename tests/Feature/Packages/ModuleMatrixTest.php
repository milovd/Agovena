<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Models\AgovenaModule;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('module enable fails when optional module is not installed', function () {
    AgovenaModule::query()->where('module_id', 'events')->delete();

    expect(fn () => app(ModuleManager::class)->enable('events'))
        ->toThrow(ValidationException::class, 'Install Module events before enabling it.');
});

test('core admin and account work with zero optional modules enabled', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->toContain('inventory-stocks')
        ->and($nav)->toContain('shipping-methods')
        ->and($nav)->not->toContain('digital-assets')
        ->and($nav)->not->toContain('digital-delivery-secrets')
        ->and($nav)->toContain('subscriptions')
        ->and($nav)->not->toContain('provisioning')
        ->and($nav)->not->toContain('events')
        ->and($nav)->not->toContain('events-checkin')
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
        ->and($accountNav)->toContain('subscriptions')
        ->and($accountNav)->not->toContain('services');

    $this->actingAs($staff)->get('/admin/inventory')->assertOk();
    $this->actingAs($customer->user)->get('/account/downloads')->assertNotFound();
    $this->actingAs($customer->user)->get('/account/digital-secrets')->assertNotFound();
});

test('module navigation is hidden when the owning module is disabled', function () {
    installAndEnableModule('provisioning');
    expect(app(ModuleManager::class)->isEnabled('provisioning'))->toBeTrue();

    app(ModuleManager::class)->disable('provisioning');
    expect(app(ModuleManager::class)->isEnabled('provisioning'))->toBeFalse();

    /** @var AdminRegistrar $admin */
    $admin = app(AdminRegistrar::class);
    $admin->navigation(new NavigationItem(
        id: 'provisioning',
        label: 'admin.nav.provisioning',
        group: 'admin.nav_groups.operations',
        href: '/admin/provisioning',
        icon: 'server',
        sort: 15,
        permission: 'provisioning.view',
    ));

    $visible = AdminNavigation::filterVisible(collect($admin->navigationItems()), app(ModuleManager::class));

    expect($visible->pluck('id'))->not->toContain('provisioning');
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
    'downloads' => ['downloads', '/admin/digital/assets'],
    'digital-delivery' => ['digital-delivery', '/admin/digital-delivery/secrets'],
    'provisioning' => ['provisioning', '/admin/provisioning'],
    'events' => ['events', '/admin/events'],
]);

test('events navigation belongs to fulfillment while product setup and check in stay distinct', function () {
    enableFirstPartyModules(['events']);

    $items = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');
    $tabs = collect(app(AdminRegistrar::class)->productTabs())->keyBy('id');

    expect($items->get('events')?->label)->toBe('admin.nav.events')
        ->and($items->get('events')?->group)->toBe('admin.nav_groups.fulfillment')
        ->and($items->get('events')?->href)->toBe('/admin/events')
        ->and($items->get('events')?->permission)->toBe('events.view')
        ->and($items->get('events')?->parent)->toBeNull()
        ->and($tabs->get('events')?->permission)->toBe('events.view')
        ->and($items->get('events-checkin')?->group)->toBe('admin.nav_groups.fulfillment')
        ->and($items->get('events-checkin')?->href)->toBe('/admin/events/check-in')
        ->and($items->get('events-checkin')?->permission)->toBe('events.checkin')
        ->and($items->get('events-checkin')?->moduleId)->toBe('events')
        ->and($items->get('events-checkin')?->parent)->toBeNull()
        ->and($items->get('tickets')?->group)->toBe('admin.nav_groups.customers');
});

test('events and check in sidebar links and routes follow separate staff permissions', function () {
    enableFirstPartyModules(['events']);

    $eventStaff = $this->createStaff(permissions: ['dashboard.view', 'events.view']);
    $this->actingAs($eventStaff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('href="/admin/events"', false)
        ->assertDontSee('href="/admin/events/check-in"', false);
    $this->get('/admin/events')->assertOk();
    $this->get('/admin/events/check-in')->assertForbidden();

    $checkInStaff = $this->createStaff(permissions: ['dashboard.view', 'events.checkin']);
    $this->actingAs($checkInStaff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('href="/admin/events/check-in"', false)
        ->assertDontSee('href="/admin/events"', false);
    $this->get('/admin/events/check-in')->assertOk();
    $this->get('/admin/events')->assertForbidden();
});

test('events and check-in tabs keep separate permissions and a single usable fulfillment sidebar link', function (array $permissions, int $eventsStatus, int $checkinStatus, string $sidebarHref, int $tabCount) {
    enableFirstPartyModules(['events']);
    $staff = $this->createStaff(permissions: $permissions);
    $this->actingAs($staff);

    $events = $this->get('/admin/events')->assertStatus($eventsStatus);
    $checkin = $this->get('/admin/events/check-in')->assertStatus($checkinStatus);
    $page = $eventsStatus === 200 ? $events : $checkin;

    $document = new DOMDocument;
    @$document->loadHTML($page->getContent());
    $xpath = new DOMXPath($document);
    $sidebar = $xpath->query('//div[contains(@class, "admin-nav__section") and contains(@data-nav-key, "nav-groupsfulfillment")]//a[@href="/admin/events" or @href="/admin/events/check-in"]');
    expect($sidebar)->toHaveCount(1)
        ->and($sidebar->item(0)->getAttribute('href'))->toBe($sidebarHref);

    $tabs = $xpath->query('//nav[@aria-label="'.__('events::admin.tabs_label').'"]//a');
    expect($tabs)->toHaveCount($tabCount)
        ->and($xpath->query('//nav[@aria-label="'.__('events::admin.tabs_label').'"]//a[@aria-current="page"]'))->toHaveCount(1);

    if ($eventsStatus === 200 && $checkinStatus === 200) {
        $checkinDocument = new DOMDocument;
        @$checkinDocument->loadHTML($checkin->getContent());
        $checkinXpath = new DOMXPath($checkinDocument);
        expect($checkinXpath->query('//nav[@aria-label="'.__('events::admin.tabs_label').'"]//a[@href="'.route('admin.events.checkin').'" and @aria-current="page"]'))->toHaveCount(1);
        expect($checkinXpath->query('//nav[contains(@class, "admin-nav")]//a[@href="/admin/events" and contains(@class, "admin-nav__link--active") and not(@aria-current)]'))->toHaveCount(1);
    }
})->with([
    'view only' => [['events.view'], 200, 403, '/admin/events', 1],
    'checkin only' => [['events.checkin'], 403, 200, '/admin/events/check-in', 1],
    'both' => [['events.view', 'events.checkin'], 200, 200, '/admin/events', 2],
]);

test('staff without Events permissions sees neither destination and cannot open either route', function () {
    enableFirstPartyModules(['events']);
    $staff = $this->createStaff(permissions: ['dashboard.view']);
    $html = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav[contains(@class, "admin-nav")]//a[@href="/admin/events" or @href="/admin/events/check-in"]'))->toHaveCount(0);
    $this->get('/admin/events')->assertForbidden();
    $this->get('/admin/events/check-in')->assertForbidden();
});

test('disabled events module removes both sidebar destinations without touching other modules', function () {
    enableFirstPartyModules(['events']);
    app(ModuleManager::class)->disable('events');

    $items = AdminNavigation::filterVisible(
        collect(app(AdminRegistrar::class)->navigationItems()),
        app(ModuleManager::class),
    )->pluck('id');

    expect($items)->not->toContain('events', 'events-checkin')
        ->and($items)->toContain('shipping-methods');
});

test('all first-party modules together expose admin and account surfaces', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    enableFirstPartyModules(['downloads', 'digital-delivery', 'provisioning', 'events']);

    $nav = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');
    expect($nav)->toContain('inventory-stocks')
        ->and($nav)->toContain('digital-assets')
        ->and($nav)->toContain('digital-delivery-secrets')
        ->and($nav)->toContain('subscriptions')
        ->and($nav)->toContain('provisioning')
        ->and($nav)->toContain('events')
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

<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminNavigationNode;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\AgovenaModule;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin navigation nests children under parents and promotes orphans', function () {
    $items = collect([
        new NavigationItem(id: 'products', label: 'admin.nav.products', group: 'admin.nav_groups.catalog', href: '/admin/products', sort: 10),
        new NavigationItem(id: 'categories', label: 'admin.nav.categories', group: 'admin.nav_groups.catalog', href: '/admin/categories', sort: 15, parent: 'products'),
        new NavigationItem(id: 'orphan', label: 'admin.nav.pages', group: 'admin.nav_groups.catalog', href: '/admin/orphan', sort: 20, parent: 'missing'),
    ]);

    $nodes = AdminNavigation::nest($items);

    expect($nodes)->toHaveCount(2)
        ->and($nodes[0])->toBeInstanceOf(AdminNavigationNode::class)
        ->and($nodes[0]->item->id)->toBe('products')
        ->and($nodes[0]->children)->toHaveCount(1)
        ->and($nodes[0]->children[0]->id)->toBe('categories')
        ->and($nodes[1]->item->id)->toBe('orphan')
        ->and($nodes[1]->children)->toBe([]);
});

test('core admin items register in their sections without parent pages', function () {
    $items = collect(app(AdminRegistrar::class)->navigationItems());
    $byId = $items->keyBy('id');

    expect($byId->get('orders')?->group)->toBe('admin.nav_groups.sales')
        ->and($byId->get('shipping-returns')?->group)->toBe('admin.nav_groups.sales')
        ->and($byId->get('products')?->group)->toBe('admin.nav_groups.catalog')
        ->and($byId->get('customers')?->group)->toBe('admin.nav_groups.customers')
        ->and($byId->get('discounts')?->group)->toBe('admin.nav_groups.marketing')
        ->and($byId->get('referrals')?->group)->toBe('admin.nav_groups.marketing')
        ->and($byId->get('themes')?->group)->toBe('admin.nav_groups.online_store')
        ->and($byId->get('modules')?->group)->toBe('admin.nav_groups.integrations')
        ->and($byId->get('settings')?->group)->toBe('admin.nav_groups.settings')
        ->and($byId->get('roles')?->group)->toBe('admin.nav_groups.settings')
        ->and($byId->get('audit')?->group)->toBe('admin.nav_groups.monitoring')
        ->and($byId->get('updates')?->group)->toBe('admin.nav_groups.system')
        ->and($items->pluck('parent')->filter()->all())->toBe([]);
});

test('every core navigation item opens for staff holding only its permission', function () {
    $items = collect(app(AdminRegistrar::class)->navigationItems())
        ->filter(fn (NavigationItem $item): bool => $item->permission !== null
            && $item->href !== null
            && str_starts_with($item->href, '/admin'));

    expect($items)->not->toBeEmpty();

    $forbidden = [];
    foreach ($items as $item) {
        $staff = $this->createStaff(permissions: [$item->permission]);
        $status = $this->actingAs($staff)->get($item->href)->getStatusCode();

        if ($status === 403) {
            $forbidden[] = "{$item->id} ({$item->permission})";
        }
    }

    expect($forbidden)->toBe([]);
});

test('database backups has one system navigation item', function () {
    $byId = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');

    expect($byId->get('backups')?->label)->toBe('admin.nav.backups')
        ->and($byId->get('backups')?->href)->toBe('/admin/backups')
        ->and($byId->get('backups')?->permission)->toBe('backups.view');
});

test('admin sidebar renders dropdown sections with their pages', function () {
    installAndEnableModule('inventory');
    installAndEnableModule('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.nav.categories'), false)
        ->assertSee(__('admin.nav_groups.commerce'), false)
        ->assertSee(__('admin.nav_groups.online_store'), false)
        ->getContent();

    expect($html)->toContain('aria-controls="nav-group-panel-adminnav-groupscatalog"')
        ->and($html)->toContain('agovena.admin.nav.group.v8.adminnav-groupscatalog')
        ->and($html)->not->toContain('admin-nav__toggle')
        ->and($html)->not->toContain('agovena.admin.nav.v6.');
});

test('a section opens for the page the staff member is on and stays folded otherwise', function () {
    $staff = $this->createStaff();
    $dashboard = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();
    $categories = $this->actingAs($staff)->get('/admin/categories')->assertOk()->getContent();

    expect($dashboard)->toMatch('/data-nav-key="agovena\.admin\.nav\.group\.v8\.adminnav-groupscatalog"\s+data-open="false"\s+data-active="false"/s')
        ->and($categories)->toMatch('/data-nav-key="agovena\.admin\.nav\.group\.v8\.adminnav-groupscatalog"\s+data-open="false"\s+data-active="true"/s')
        ->and($categories)->toMatch('#class="admin-nav__sub-link admin-nav__sub-link--active"\s+href="/admin/categories"\s+aria-current="page"#');
});

test('the real Admin groups registered destinations into the commerce dropdown sections', function () {
    $items = collect(app(AdminRegistrar::class)->navigationItems());
    $groups = AdminNavigation::groupedTree($items);
    $rendered = $groups->flatMap(fn ($nodes) => array_map(fn (NavigationItem $item) => $item->id, AdminNavigation::groupLinks($nodes)));

    expect(AdminNavigation::groupOrder())->toBe([
        'admin.nav_groups.overview',
        'admin.nav_groups.commerce',
        'admin.nav_groups.catalog',
        'admin.nav_groups.customers',
        'admin.nav_groups.marketing',
        'admin.nav_groups.fulfillment',
        'admin.nav_groups.online_store',
        'admin.nav_groups.integrations',
        'admin.nav_groups.settings',
        'admin.nav_groups.system',
    ])->and($groups->keys()->all())->toBe(AdminNavigation::groupOrder())
        ->and($rendered->sort()->values()->all())->toBe($items->pluck('id')->sort()->values()->all())
        ->and($groups->get('admin.nav_groups.commerce')->pluck('item.id'))->toContain('orders', 'invoices', 'shipping-returns')
        ->and($groups->get('admin.nav_groups.system')->pluck('item.id'))->toContain('updates', 'audit', 'failed-jobs')
        ->and($groups->get('admin.nav_groups.online_store')->pluck('item.id'))->toContain('pages');
});

test('legacy Module group keys resolve before grouping without rewriting their registrations', function () {
    $items = collect([
        new NavigationItem(id: 'third-party-service', label: 'Service', group: 'admin.nav_groups.operations', href: '/admin/service'),
        new NavigationItem(id: 'third-party-settings', label: 'Settings', group: 'admin.nav_groups.configuration', href: '/admin/settings'),
        new NavigationItem(id: 'third-party-support', label: 'Support', group: 'admin.nav_groups.support', href: '/admin/support'),
        new NavigationItem(id: 'third-party-store', label: 'Store', group: 'admin.nav_groups.appearance', href: '/admin/store'),
    ]);
    $groups = AdminNavigation::groupedTree($items);

    expect($groups->keys()->all())->toBe([
        'admin.nav_groups.customers',
        'admin.nav_groups.fulfillment',
        'admin.nav_groups.online_store',
        'admin.nav_groups.settings',
    ])->and($groups->flatten(1)->pluck('item.id')->sort()->values()->all())
        ->toBe($items->pluck('id')->sort()->values()->all())
        ->and($items[0]->group)->toBe('admin.nav_groups.operations');
});

test('new navigation groups have real English and Dutch labels', function () {
    expect(__('admin.nav_groups.fulfillment', [], 'en'))->toBe('Fulfillment')
        ->and(__('admin.nav_groups.fulfillment', [], 'nl'))->toBe('Levering')
        ->and(__('admin.nav_groups.online_store', [], 'en'))->toBe('Online store')
        ->and(__('admin.nav_groups.online_store', [], 'nl'))->toBe('Webshop')
        ->and(__('admin.nav_groups.integrations', [], 'en'))->toBe('Integrations')
        ->and(__('admin.nav_groups.integrations', [], 'nl'))->toBe('Integraties')
        ->and(__('admin.nav_groups.monitoring', [], 'en'))->toBe('Logs & monitoring')
        ->and(__('admin.nav_groups.monitoring', [], 'nl'))->toBe('Logs & monitoring');
});

test('a custom third-party group stays visible under its own key', function () {
    $item = new NavigationItem(id: 'third-party-custom', label: 'Special', group: 'module::admin.custom_group', href: '/admin/custom');

    expect(AdminNavigation::groupedTree(collect([$item]))->keys()->all())->toBe(['module::admin.custom_group']);
});

test('a Module-owned item keeps its registered custom group even when its ID matches a first-party capability', function () {
    $item = new NavigationItem(id: 'events', label: 'Events', group: 'module::admin.events', href: '/admin/events');

    expect(AdminNavigation::groupedTree(collect([$item]))->keys()->all())->toBe(['module::admin.events']);
});

test('core commerce links remain in the sidebar without retired module rows', function () {
    AgovenaModule::query()->whereIn('module_id', ['inventory', 'shipping', 'subscriptions'])->delete();
    app(SyncRegisteredPermissions::class)(force: true);

    $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('href="/admin/inventory"', false)
        ->assertSee('href="/admin/shipping/methods"', false)
        ->assertSee('href="/admin/shipping/returns"', false)
        ->assertSee('href="/admin/subscriptions"', false)
        ->assertDontSee('href="/admin/plan-changes"', false);

    $this->get(route('admin.plan-changes.index'))
        ->assertOk()
        ->assertSee('href="'.route('admin.plan-changes.index').'"', false);
});

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

test('core admin items are grouped as sibling links instead of nested parents', function () {
    $byId = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');

    expect($byId->get('products')?->group)->toBe('admin.nav_groups.catalog')
        ->and($byId->get('categories')?->group)->toBe('admin.nav_groups.catalog')
        ->and($byId->get('categories')?->parent)->toBeNull()
        ->and($byId->get('orders')?->group)->toBe('admin.nav_groups.sales')
        ->and($byId->get('invoices')?->parent)->toBeNull()
        ->and($byId->get('discounts')?->parent)->toBeNull()
        ->and($byId->get('customers')?->group)->toBe('admin.nav_groups.customers')
        ->and($byId->get('customer-properties')?->parent)->toBeNull()
        ->and($byId->get('theme-customize')?->parent)->toBeNull()
        ->and($byId->get('navigation')?->parent)->toBeNull()
        ->and($byId->get('pages')?->parent)->toBeNull()
        ->and($byId->get('themes')?->group)->toBe('admin.nav_groups.online_store')
        ->and($byId->get('tickets')?->group)->toBe('admin.nav_groups.customers')
        ->and($byId->get('modules')?->group)->toBe('admin.nav_groups.integrations')
        ->and($byId->get('audit')?->group)->toBe('admin.nav_groups.monitoring')
        ->and($byId->get('extensions')?->parent)->toBeNull()
        ->and($byId->get('modules')?->parent)->toBeNull()
        ->and($byId->get('roles')?->parent)->toBeNull()
        ->and($byId->get('security')?->parent)->toBeNull()
        ->and($byId->get('api-tokens')?->parent)->toBeNull()
        ->and($byId->get('currencies')?->parent)->toBeNull()
        ->and($byId->get('taxes')?->parent)->toBeNull()
        ->and($byId->get('email-log')?->parent)->toBeNull()
        ->and($byId->get('failed-jobs')?->parent)->toBeNull()
        ->and($byId->get('notification-templates')?->parent)->toBeNull();
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

test('admin sidebar renders grouped collapsible sections with sibling links', function () {
    installAndEnableModule('inventory');
    installAndEnableModule('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.nav.categories'), false)
        ->assertSee(__('admin.nav_groups.catalog'), false)
        ->assertSee(__('admin.nav_groups.sales'), false)
        ->assertSee(__('admin.nav_groups.online_store'), false)
        ->getContent();

    expect($html)->toContain('agovena.admin.nav.v6.')
        ->and($html)->toMatch('/<button[^>]*id="nav-group-[^"]*overview"/s')
        ->and($html)->not->toContain('admin-nav__group--static')
        ->and($html)->not->toContain('admin-nav__toggle');
});

test('the first Admin visit shows overview and catalog and keeps the other groups folded', function () {
    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/data-nav-key="agovena\.admin\.nav\.v6\.adminnav-groupsoverview"\s+data-open="true"/s')
        ->and($html)->toMatch('/data-nav-key="agovena\.admin\.nav\.v6\.adminnav-groupscatalog"\s+data-open="true"/s')
        ->and($html)->toMatch('/data-nav-key="agovena\.admin\.nav\.v6\.adminnav-groupssales"\s+data-open="false"/s');
});

test('the real Admin groups registered destinations into the approved nine sections', function () {
    $items = collect(app(AdminRegistrar::class)->navigationItems());
    $groups = AdminNavigation::groupedTree($items);

    expect(AdminNavigation::groupOrder())->toBe([
        'admin.nav_groups.overview',
        'admin.nav_groups.catalog',
        'admin.nav_groups.sales',
        'admin.nav_groups.customers',
        'admin.nav_groups.fulfillment',
        'admin.nav_groups.online_store',
        'admin.nav_groups.integrations',
        'admin.nav_groups.monitoring',
        'admin.nav_groups.system',
    ])->and($groups->keys()->all())->toBe(AdminNavigation::groupOrder())
        ->and($groups->flatten(1)->pluck('item.id')->sort()->values()->all())
        ->toBe($items->pluck('id')->sort()->values()->all())
        ->and($groups->get('admin.nav_groups.customers')->pluck('item.id'))->toContain('tickets')
        ->and($groups->get('admin.nav_groups.integrations')->pluck('item.id'))->toContain('modules', 'webhooks')
        ->and($groups->get('admin.nav_groups.monitoring')->pluck('item.id'))->toContain('audit', 'failed-jobs')
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
        'admin.nav_groups.system',
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
        ->assertSee('href="/admin/plan-changes"', false);
});

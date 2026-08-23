<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminNavigationNode;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Permissions\SyncRegisteredPermissions;
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
        ->and($byId->get('themes')?->group)->toBe('admin.nav_groups.appearance')
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
        ->assertSee(__('admin.nav_groups.appearance'), false)
        ->getContent();

    expect($html)->toContain('agovena.admin.nav.v6.')
        ->and($html)->not->toContain('admin-nav__toggle');
});

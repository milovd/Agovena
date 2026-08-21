<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminNavigationNode;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin navigation nests children under parents and promotes orphans', function () {
    $items = collect([
        new NavigationItem(id: 'products', label: 'admin.nav.products', group: 'admin.nav_groups.commerce', href: '/admin/products', sort: 10),
        new NavigationItem(id: 'categories', label: 'admin.nav.categories', group: 'admin.nav_groups.commerce', href: '/admin/categories', sort: 15, parent: 'products'),
        new NavigationItem(id: 'orphan', label: 'admin.nav.pages', group: 'admin.nav_groups.commerce', href: '/admin/orphan', sort: 20, parent: 'missing'),
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

test('commerce and system items are nested behind dropdown parents', function () {
    $byId = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');

    expect($byId->get('categories')?->parent)->toBe('products')
        ->and($byId->get('invoices')?->parent)->toBe('orders')
        ->and($byId->get('discounts')?->parent)->toBe('orders')
        ->and($byId->get('customer-properties')?->parent)->toBe('customers')
        ->and($byId->get('theme-customize')?->parent)->toBe('themes')
        ->and($byId->get('navigation')?->parent)->toBe('themes')
        ->and($byId->get('pages')?->parent)->toBe('themes')
        ->and($byId->get('themes')?->group)->toBe('admin.nav_groups.appearance')
        ->and($byId->get('extensions')?->parent)->toBe('modules')
        ->and($byId->get('store-presets')?->parent)->toBe('modules')
        ->and($byId->get('roles')?->parent)->toBe('users')
        ->and($byId->get('security')?->parent)->toBe('users')
        ->and($byId->get('api-tokens')?->parent)->toBe('users')
        ->and($byId->get('currencies')?->parent)->toBe('settings')
        ->and($byId->get('taxes')?->parent)->toBe('settings')
        ->and($byId->get('email-log')?->parent)->toBe('audit')
        ->and($byId->get('failed-jobs')?->parent)->toBe('audit')
        ->and($byId->get('notification-templates')?->parent)->toBe('audit');
});

test('admin sidebar renders nested collapsible branches instead of a flat list', function () {
    app(ModuleManager::class)->enable('inventory');
    app(ModuleManager::class)->enable('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('admin-nav__branch', false)
        ->assertSee('admin-nav__sub', false)
        ->assertSee(__('admin.nav.categories'), false)
        ->assertSee(__('admin.nav_groups.appearance'), false)
        ->getContent();

    expect($html)->toContain('agovena.admin.nav.v5.')
        ->and($html)->toContain('admin-nav__toggle');
});

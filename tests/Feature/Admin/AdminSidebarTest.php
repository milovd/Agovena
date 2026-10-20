<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\User;
use Illuminate\Support\Collection;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * The sidebar follows the commerce-admin model: the dashboard, then dropdown sections that are
 * never pages themselves (Commerce: orders, invoices, returns), with every destination reachable.
 */

/** @return Collection<string, list<string>> */
function sidebarSectionsFor(?User $staff): Collection
{
    $items = AdminNavigation::filterForStaff(
        AdminNavigation::filterVisible(collect(app(AdminRegistrar::class)->navigationItems()), app(ModuleManager::class)),
        $staff,
    );

    return AdminNavigation::groupedTree($items)->map(
        fn (Collection $nodes): array => array_map(fn (NavigationItem $item): string => $item->id, AdminNavigation::groupLinks($nodes)),
    );
}

function sidebarHtmlFor(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('the sidebar shows the dashboard and commerce-style dropdown sections', function (): void {
    expect(sidebarSectionsFor($this->createStaff())->all())->toBe([
        'admin.nav_groups.overview' => ['dashboard'],
        'admin.nav_groups.commerce' => ['orders', 'invoices', 'shipping-returns', 'subscriptions'],
        'admin.nav_groups.catalog' => ['products', 'categories', 'inventory-stocks'],
        'admin.nav_groups.customers' => ['customers', 'tickets'],
        'admin.nav_groups.marketing' => ['discounts', 'referrals'],
        'admin.nav_groups.fulfillment' => ['shipping-methods'],
        'admin.nav_groups.online_store' => ['themes', 'theme-customize', 'navigation', 'pages'],
        'admin.nav_groups.integrations' => ['modules', 'extensions', 'webhooks', 'api-tokens'],
        'admin.nav_groups.settings' => ['settings', 'currencies', 'taxes', 'notification-templates', 'roles'],
        'admin.nav_groups.system' => ['updates', 'backups', 'exports', 'maintenance', 'audit', 'email-log', 'cron-statistics', 'failed-jobs'],
    ]);
});

test('regrouping keeps every registered Admin destination in the sidebar for an owner', function (): void {
    $registered = collect(app(AdminRegistrar::class)->navigationItems());
    $owner = $this->createStaff();
    $visible = AdminNavigation::filterForStaff(AdminNavigation::filterVisible($registered, app(ModuleManager::class)), $owner);

    expect(sidebarSectionsFor($owner)->flatten()->sort()->values()->all())->toBe($visible->pluck('id')->sort()->values()->all());
});

test('dropdown sections have distinct icons so the icon-only sidebar stays recognisable', function (): void {
    $groups = AdminNavigation::groupedTree(collect(app(AdminRegistrar::class)->navigationItems()));
    $icons = $groups->filter(fn ($nodes, string $group): bool => AdminNavigation::groupHasLabel($group))
        ->map(fn ($nodes, string $group): string => AdminNavigation::groupIcon($group, $nodes));

    expect($icons->count())->toBe(9)
        ->and($icons->duplicates()->values()->all())->toBe([]);
});

test('staff only see the pages they may open, inside their sections', function (): void {
    $staff = $this->createStaff(permissions: ['dashboard.view', 'invoices.view', 'tickets.view']);

    expect(sidebarSectionsFor($staff)->all())->toBe([
        'admin.nav_groups.overview' => ['dashboard'],
        'admin.nav_groups.commerce' => ['invoices'],
        'admin.nav_groups.customers' => ['tickets'],
    ]);
});

test('fulfillment starts with Core shipping before optional services', function (): void {
    installAndEnableModules(['downloads', 'events', 'provisioning']);
    app(SyncRegisteredPermissions::class)(force: true);
    $fulfillment = sidebarSectionsFor($this->createStaff())->get('admin.nav_groups.fulfillment');

    expect(count($fulfillment))->toBeGreaterThan(3)
        ->and($fulfillment[0])->toBe('shipping-methods');
});

test('sections are dropdown buttons, not pages, and their pages are plain sub-links', function (): void {
    $html = $this->actingAs($this->createStaff())->get('/admin/invoices')->assertOk()->getContent();
    $xpath = sidebarHtmlFor($html);
    $commerce = $xpath->query('//li[@data-nav-group="adminnav-groupscommerce"]')->item(0);

    expect($commerce)->not->toBeNull()
        ->and($xpath->query('./button[@aria-controls="nav-group-panel-adminnav-groupscommerce"][@id="nav-group-adminnav-groupscommerce"]', $commerce)->length)->toBe(1)
        ->and($xpath->query('./a', $commerce)->length)->toBe(0)
        ->and($xpath->query('.//ul[@class="admin-nav__sub"]//svg', $commerce)->length)->toBe(0)
        ->and($xpath->query('.//ul[@class="admin-nav__sub"]//a/@href', $commerce)->item(0)?->nodeValue)->toBe('/admin/orders')
        ->and($xpath->query('.//a[@aria-current="page"]', $commerce)->item(0)?->getAttribute('href'))->toBe('/admin/invoices')
        ->and($commerce->getAttribute('data-active'))->toBe('true')
        ->and($xpath->query('//p[contains(@class, "admin-nav__group")]')->length)->toBe(0)
        ->and($xpath->query('//li[@data-nav-group="adminnav-groupscatalog"]')->item(0)?->getAttribute('data-active'))->toBe('false');
});

test('the dashboard is a direct link without a section', function (): void {
    $xpath = sidebarHtmlFor($this->actingAs($this->createStaff())->get('/admin')->assertOk()->getContent());

    expect($xpath->query('//nav[@class="admin-nav"]/ul/li/a[@href="/admin"][@aria-current="page"]')->length)->toBe(1)
        ->and($xpath->query('//li[@data-nav-group="adminnav-groupsoverview"]')->length)->toBe(0);
});

test('the collapse toggle sits beside the store name and the support links are part of the navigation', function (): void {
    $html = $this->actingAs($this->createStaff())->get(route('admin.dashboard'))->assertOk()->getContent();
    $xpath = sidebarHtmlFor($html);

    expect($xpath->query('//div[@class="admin-sidebar__header"]/a[@class="admin-sidebar__brand"]/following-sibling::button[@class="admin-sidebar__toggle"][@aria-label="'.__('common.toggle_sidebar').'"]')->length)->toBe(1)
        ->and($xpath->query('//header[contains(@class, "admin-topbar")]//button[@aria-label="'.__('common.toggle_sidebar').'"]')->length)->toBe(0)
        ->and($xpath->query('//div[@class="admin-sidebar__scroll"]/nav[@class="admin-nav"]//a[contains(@class, "admin-sidebar__footer-link")]')->length)->toBe(3)
        ->and($html)->toContain(__('admin.sidebar_links.sponsor'))
        ->toContain(__('admin.sidebar_links.github'))
        ->toContain(__('admin.sidebar_links.documentation'))
        ->not->toContain(__('admin.sidebar_powered_by', ['year' => now()->year]))
        ->toContain("localStorage.getItem('agovena.admin.sidebar')")
        ->toContain('@agovena-sidebar-expand.window="expandSidebar()"');
});

test('the sidebar collapses to an icon rail only on desktop widths', function (): void {
    $css = cssWithImports(resource_path('css/admin.css'));
    $js = (string) file_get_contents(resource_path('js/admin/shell.js'));

    expect($css)->toContain('--ag-sidebar-width: 16rem;')
        ->toContain('--ag-sidebar-width-icon: 3rem;')
        ->toMatch('/@media \(min-width: 901px\) \{\s*html\[data-sidebar="collapsed"\] \.admin-shell \{\s*grid-template-columns: var\(--ag-sidebar-width-icon\) 1fr;/')
        ->toMatch('/html\[data-sidebar="collapsed"\] \.admin-nav__trigger--active \{[^}]*background: var\(--ag-color-sidebar-active\);/')
        ->toMatch('/\.admin-sidebar__footer \{[^}]*margin-top: auto;/')
        ->and($js)->toContain('revealCurrentNavItem')
        ->toContain("new CustomEvent('agovena-sidebar-expand')");
});

test('the sidebar shares the surface tone in both colour modes so the Admin reads as one whole', function (): void {
    $theme = (string) file_get_contents(base_path('themes/default/resources/css/admin/_tokens.css'));
    $core = (string) file_get_contents(resource_path('css/admin/settings/_tokens.css'));
    $dark = substr($theme, (int) strpos($theme, '[data-theme="dark"]'));

    expect(substr_count($theme, '--ag-color-sidebar: var(--ag-color-surface);'))->toBe(2)
        ->and($dark)->toContain('--ag-color-sidebar: var(--ag-color-surface);')
        ->and($core)->toContain('--ag-color-sidebar: var(--ag-color-surface);');
});

/** WCAG relative luminance of a #rrggbb colour. */
function adminTokenLuminance(string $hex): float
{
    $channels = array_map(static function (string $pair): float {
        $value = hexdec($pair) / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split(ltrim($hex, '#'), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function adminTokenContrast(string $a, string $b): float
{
    [$high, $low] = [max(adminTokenLuminance($a), adminTokenLuminance($b)), min(adminTokenLuminance($a), adminTokenLuminance($b))];

    return ($high + 0.05) / ($low + 0.05);
}

test('dark mode card borders are as quiet as light mode card borders', function (): void {
    $css = (string) file_get_contents(base_path('themes/default/resources/css/admin/_tokens.css'));
    $split = (int) strpos($css, '[data-theme="dark"]');
    $token = static function (string $block, string $name): string {
        preg_match('/'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6});/', $block, $match);

        return $match[1] ?? '';
    };
    [$light, $dark] = [substr($css, 0, $split), substr($css, $split)];

    $lightRatio = adminTokenContrast($token($light, '--ag-color-border'), $token($light, '--ag-color-surface'));
    $darkRatio = adminTokenContrast($token($dark, '--ag-color-border'), $token($dark, '--ag-color-surface'));

    expect(abs($darkRatio - $lightRatio))->toBeLessThan(0.03);
});

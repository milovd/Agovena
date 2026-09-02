<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardMetrics;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Theme\StorefrontBrand;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Dashboard;
use App\Models\Customer;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin shell uses account icon trigger and leave admin action', function () {
    $staff = $this->createStaff();

    $html = $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('admin.view_storefront'), false)
        ->assertSee(__('admin.product_name'), false)
        ->assertSee('/'.StorefrontBrand::BUNDLED_LOGO, false)
        ->assertSee(__('admin.exit_admin'), false)
        ->assertSee(route('storefront.home'), false)
        ->assertSee(__('admin.nav_groups.system'), false)
        ->assertSee(__('admin.nav.customer_properties'), false)
        ->assertSee(__('admin.sidebar_powered_by', ['year' => now()->year]), false)
        ->assertSee(__('admin.sidebar_links.sponsor'), false)
        ->assertSee(__('admin.sidebar_links.github'), false)
        ->assertSee(__('admin.sidebar_links.documentation'), false)
        ->assertSee(config('agovena.admin.support_links.sponsor'), false)
        ->assertSee(config('agovena.admin.support_links.github'), false)
        ->assertSee(config('agovena.admin.support_links.documentation'), false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('admin-account-trigger', false)
        ->getContent();

    expect(preg_match_all('/class="admin-sidebar__footer-link admin-sidebar__footer-link--/', $html))->toBe(3);
    expect($html)->toContain('<div class="admin-sidebar__scroll">');
    expect(strpos($html, 'admin-sidebar__scroll'))->toBeLessThan(strpos($html, 'admin-sidebar__footer'));
});

test('dashboard renders real metrics without fake trends', function () {
    Livewire::actingAs($this->createStaff())
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('admin.dashboard.heading'), false)
        ->assertSee(__('admin.dashboard.stats.orders'), false)
        ->assertSee(__('admin.dashboard.charts.revenue'), false);
});

test('dashboard keeps six focused metrics and one configurable chart', function () {
    $component = Livewire::actingAs($this->createStaff())
        ->test(Dashboard::class)
        ->assertOk();
    $html = $component->html();

    expect(substr_count($html, 'class="ag-metric"'))->toBe(6)
        ->and(substr_count($html, 'x-data="agChart'))->toBe(1)
        ->and(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and($html)->toContain(htmlspecialchars((string) __('admin.dashboard.charts.overview'), ENT_QUOTES, 'UTF-8'))
        ->and($html)->toContain('wire:click="setChartRange(\'7\')"')
        ->and($html)->toContain('wire:click="setChartRange(\'month\')"')
        ->and($html)->toContain('id="dashboard-chart-type"')
        ->and($html)->toContain('wire:model.live="chartType"')
        ->and($html)->toContain('var(--ag-color-chart-4)')
        ->and($html)->not->toContain(__('admin.dashboard.stats.open_tickets'), false);

    $component
        ->call('setChartRange', '7')
        ->assertSet('chartRange', '7')
        ->assertSee('dashboard-chart-7-line', false)
        ->set('chartType', 'bar')
        ->assertSet('chartType', 'bar')
        ->assertSee('dashboard-chart-7-bar', false);
});

test('dashboard normalizes unsupported chart state before rendering', function () {
    $component = Livewire::actingAs($this->createStaff())
        ->test(Dashboard::class)
        ->set('chartRange', 'unsupported')
        ->assertSet('chartRange', '14')
        ->set('chartType', 'scatter')
        ->assertSet('chartType', 'line');
});

test('dashboard chart ranges return the requested number of daily points', function () {
    $this->createStaff();
    $metrics = app(DashboardMetrics::class);

    expect($metrics->build('7')['revenueSeries']['labels'])->toHaveCount(7)
        ->and($metrics->build('14')['revenueSeries']['labels'])->toHaveCount(14)
        ->and($metrics->build('90')['revenueSeries']['labels'])->toHaveCount(90)
        ->and(count($metrics->build('month')['revenueSeries']['labels']))->toBeBetween(1, 31);
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
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $item = collect(app(AdminRegistrar::class)->navigationItems())
        ->firstWhere('id', 'subscriptions');

    expect($item)->not->toBeNull()
        ->and($item->group)->toBe('admin.nav_groups.operations');
});

test('admin navigation groups are collapsible and fulfillment icons are distinct', function () {
    installAndEnableModule('digital');
    installAndEnableModule('inventory');
    installAndEnableModule('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $items = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');

    expect($items->get('digital-assets')?->icon)->toBe('download')
        ->and($items->get('inventory-stocks')?->icon)->toBe('warehouse')
        ->and($items->get('shipping-methods')?->icon)->toBe('truck')
        ->and($items->get('shipping-returns')?->icon)->toBe('rotate-ccw');

    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('admin-nav__group', false)
        ->assertSee('aria-controls="nav-group-panel-', false)
        ->assertSee(__('admin.nav_groups.overview'), false)
        ->getContent();

    expect($html)->toContain('agovena.admin.nav.v6.')
        ->and($html)->toContain('open: true');
});

test('admin product pagination uses sized icons not unbounded svg chevrons', function () {
    $staff = $this->createStaff();
    Product::factory()->count(16)->active()->create();

    $html = $this->actingAs($staff)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee('ag-pagination', false)
        ->assertSee('ag-pagination__icon', false)
        ->assertDontSee('class="w-5 h-5"', false)
        ->getContent();

    expect(preg_match_all('/<svg[^>]*class="w-5 h-5"/', $html))->toBe(0)
        ->and(preg_match_all('/ag-pagination__icon"[^>]*width="16"/', $html))->toBeGreaterThan(0);
});

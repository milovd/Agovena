<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardMetrics;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\StorefrontBrand;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Dashboard;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('Admin pages have one content H1 and the topbar acts as a breadcrumb', function () {
    $staff = $this->createStaff();

    foreach (['admin.dashboard', 'admin.appearance.pages', 'admin.appearance.navigation', 'admin.currencies.index', 'admin.roles.index', 'admin.referrals.index'] as $name) {
        $html = $this->actingAs($staff)->get(route($name))->assertOk()->getContent();

        expect($html)->toMatch('/<nav\s+class="admin-topbar__breadcrumb"[^>]*>/s')
            ->and($html)->toMatch('/<main\b[^>]*>.*?<h1\b[^>]*class="admin-page__heading"/s')
            ->and(preg_match_all('/<h1\b/i', $html))->toBe(1);
    }
});

test('Admin breadcrumb has a dedicated responsive shell style', function () {
    $css = file_get_contents(resource_path('css/admin/components/_admin-shell.css'));

    expect($css)->toContain('.admin-topbar__breadcrumb {')
        ->and($css)->toContain('.admin-topbar__breadcrumb [aria-current="page"] {');
});

test('mobile Admin drawer exposes modal semantics and keeps background inert only while open', function () {
    $staff = $this->createStaff();
    $html = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('x-ref="sidebar"')
        ->and($html)->toContain(":role=\"navOpen && isMobile ? 'dialog' : null\"")
        ->and($html)->toContain(":aria-modal=\"navOpen && isMobile ? 'true' : null\"")
        ->and($html)->toContain(':inert="isMobile && !navOpen"')
        ->and($html)->toContain(':inert="isMobile && navOpen"')
        ->and($html)->toContain('@keydown.tab="trapNavFocus($event)"');
});

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
        ->and($html)->toContain('wire:key="metric-aov"')
        ->and($html)->toContain('href="'.route('admin.orders.index').'">View details', false);

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

test('dashboard chart renders filled revenue bars and selected line markers', function () {
    $view = file_get_contents(resource_path('views/livewire/admin/dashboard.blade.php'));
    $script = file_get_contents(resource_path('js/admin/chart.js'));
    $entry = file_get_contents(resource_path('js/admin.js'));

    expect($entry)->toContain("import { registerChartComponents } from './admin/chart.js';")
        ->and($entry)->toMatch('/^\s*registerChartComponents\(Alpine\);/m')
        ->and($view)->toContain("'barBackgroundColor' => 'var(--ag-color-chart-1)'")
        ->and($script)->toContain('buildLinePointRadii')
        ->and($script)->toContain('barBackgroundColor')
        ->and($script)->toContain('Intl.NumberFormat')
        ->and($script)->toContain('pointHitRadius');
});

test('dashboard revenue chart uses the display currency and its minor-unit precision', function () {
    Currency::query()->updateOrCreate(
        ['code' => 'JPY'],
        ['name' => 'Japanese yen', 'prefix' => '¥', 'suffix' => '', 'precision' => 0, 'exchange_rate' => '1.00000000', 'is_active' => true],
    );
    app(SettingsRepository::class)->set('general', 'base_currency', 'JPY');
    $staff = $this->createStaff();

    $series = app(DashboardMetrics::class)->build('14')['revenueSeries'];
    $html = Livewire::actingAs($staff)->test(Dashboard::class)->html();
    $script = file_get_contents(resource_path('js/admin/chart.js'));

    expect($series['currency'])->toBe('JPY')
        ->and($series['precision'])->toBe(0)
        ->and($html)->toContain('&quot;currency&quot;:&quot;JPY&quot;')
        ->and($html)->toContain('&quot;currencyPrecision&quot;:0')
        ->and($html)->not->toContain('&quot;currency&quot;:&quot;EUR&quot;')
        ->and($script)->toContain('config.currencyPrecision');
});

test('admin charts read their config from the chart root without double escaping', function () {
    $staff = $this->createStaff();
    $html = Livewire::actingAs($staff)->test(Dashboard::class)->html();
    $script = file_get_contents(resource_path('js/admin/chart.js'));

    expect($html)->toContain('data-chart-config="{&quot;type&quot;')
        ->and($html)->not->toContain('&amp;quot;')
        ->and($script)->toContain("this.\$root.matches('[data-chart-config]')")
        ->and($script)->toContain('configElement?.dataset.chartConfig');
});

test('dashboard shows prioritized support tickets and current active users', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $now = now();

    $highPriority = Ticket::query()->create([
        'number' => 'TKT-HIGH',
        'customer_id' => $customer->id,
        'subject' => 'Urgent support request',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
        'last_reply_at' => $now->copy()->subHour(),
    ]);
    Ticket::query()->create([
        'number' => 'TKT-NORMAL',
        'customer_id' => $customer->id,
        'subject' => 'Normal support request',
        'status' => TicketStatus::Answered,
        'priority' => TicketPriority::Normal,
        'last_reply_at' => $now,
    ]);
    Ticket::query()->create([
        'number' => 'TKT-CLOSED',
        'customer_id' => $customer->id,
        'subject' => 'Closed support request',
        'status' => TicketStatus::Closed,
        'priority' => TicketPriority::High,
        'last_reply_at' => $now,
    ]);

    config([
        'session.driver' => 'database',
        'session.lifetime' => 120,
    ]);
    DB::table('sessions')->insert([
        [
            'id' => 'active-staff',
            'user_id' => $staff->id,
            'payload' => '',
            'last_activity' => $now->getTimestamp(),
        ],
        [
            'id' => 'active-customer',
            'user_id' => $customer->user_id,
            'payload' => '',
            'last_activity' => $now->copy()->subMinutes(10)->getTimestamp(),
        ],
        [
            'id' => 'stale-staff',
            'user_id' => $staff->id,
            'payload' => '',
            'last_activity' => $now->copy()->subMinutes(121)->getTimestamp(),
        ],
    ]);

    $this->actingAs($staff);
    $data = app(DashboardMetrics::class)->build();
    $html = Livewire::actingAs($staff)->test(Dashboard::class)->html();

    expect($data['supportTicketCount'])->toBe(2)
        ->and($data['supportTickets']->first()?->id)->toBe($highPriority->id)
        ->and($data['activeUserCount'])->toBe(2)
        ->and($data['activeUsers'])->toHaveCount(2)
        ->and($html)->toContain(__('admin.dashboard.support.title'), false)
        ->and($html)->toContain(__('admin.dashboard.active_users.title'), false)
        ->and($html)->toContain('Urgent support request', false)
        ->and($html)->toContain('id="active-users-heading"', false)
        ->and($html)->not->toContain(__('admin.dashboard.widgets.attention'), false)
        ->and($html)->not->toContain(__('admin.dashboard.widgets.recent_orders'), false);
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

test('core physical commerce keeps fulfillment navigation available', function () {
    expect(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse();

    $ids = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');

    expect($ids)->toContain('inventory-stocks');
});

test('enabled subscriptions appear under sales navigation', function () {
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $item = collect(app(AdminRegistrar::class)->navigationItems())
        ->firstWhere('id', 'subscriptions');

    expect($item)->not->toBeNull()
        ->and($item->group)->toBe('admin.nav_groups.sales');
});

test('admin navigation groups are collapsible and fulfillment icons are distinct', function () {
    installAndEnableModule('downloads');
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
        ->and($html)->toContain('x-data="agAdminNavGroup"')
        ->and($html)->toContain('data-open="true"');
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

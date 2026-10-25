<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardMetrics;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\StorefrontBrand;
use App\Enums\PaymentStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Dashboard;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Ticket;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;
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
        ->assertDontSee(__('admin.nav.customer_properties'), false)
        ->assertDontSee(__('admin.sidebar_powered_by', ['year' => now()->year]), false)
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

test('shared Atomic Design wrappers compose existing list and package controls', function () {
    $products = file_get_contents(resource_path('views/livewire/admin/products/index.blade.php'));
    $extension = file_get_contents(resource_path('views/livewire/admin/packages/partials/extension-card.blade.php'));

    expect($products)->toContain('<x-ag.table-toolbar')
        ->and($products)->toContain('<x-ag.row-actions')
        ->and($products)->toContain('<x-ag.badge')
        ->and($extension)->toContain('<x-ag.package-card');
});

test('dashboard presents six permission-aware KPI cards with themed icon tiles', function () {
    $staff = $this->createStaff([], ['dashboard.view']);
    $html = Livewire::actingAs($staff)->test(Dashboard::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " ag-metrics ")]/article[contains(concat(" ", normalize-space(@class), " "), " ag-metric ")]'))->toHaveCount(6)
        ->and($xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " ag-metric ")]/div[contains(@class, "ag-metric__heading")]/span[contains(@class, "ag-icon-tile")]/svg'))->toHaveCount(6)
        ->and($xpath->query('//article[contains(@class, "ag-metric")]/a[contains(@class, "ag-metric__link")]'))->toHaveCount(0)
        ->and($html)->toContain('data-tone="emerald"')
        ->and($html)->toContain('data-tone="cyan"');
});

test('products list retains every filter and bulk action while presenting a visual category cell', function () {
    $source = file_get_contents(resource_path('views/livewire/admin/products/index.blade.php'));
    expect($source)->toContain('ag-table__category')
        ->and($source)->toContain('<x-ag.icon-tile')
        ->and($source)->toContain('ag-bulk-selection__count')
        ->and($source)->toContain('ag-table__mobile-meta')
        ->and($source)->toContain('wire:model.live.debounce.300ms="search"');

    foreach (['wire:model.live="status"', 'wire:model.live="category"', 'wire:model.live="sort"', 'toggleAll', "bulkSetStatus('active')", "bulkSetStatus('draft')", 'confirmDelete('] as $action) {
        expect($source)->toContain($action);
    }
});

test('package pages share a decorative hero without changing their tab controls', function () {
    foreach (['extensions', 'modules'] as $kind) {
        $source = file_get_contents(resource_path("views/livewire/admin/{$kind}/index.blade.php"));
        expect($source)->toContain('<x-ag.package-hero')
            ->and($source)->toContain('<x-ag.page-header')
            ->and($source)->toContain("@include('livewire.admin.partials.tabs'");
    }
});

test('extension catalogue uses one two-column grid with manifest category tags and a brand hero', function () {
    $source = file_get_contents(resource_path('views/livewire/admin/extensions/index.blade.php'));
    $card = file_get_contents(resource_path('views/livewire/admin/packages/partials/extension-card.blade.php'));
    $hero = file_get_contents(resource_path('views/components/ag/package-hero.blade.php'));
    $css = file_get_contents(resource_path('css/admin/screens/_packages.css'));

    expect($source)->toContain("@include('livewire.admin.packages.partials.extension-catalog'")
        ->and($card)->toContain('ag-package-card__tags')
        ->and($hero)->toContain('ag-package-hero__tile--center')
        ->and($hero)->not->toContain('ag-package-hero__piece')
        ->and($css)->toContain('grid-template-columns: repeat(2, minmax(0, 1fr))');
});

test('module rows give package artwork a portrait well without enlarging generic package marks', function () {
    $css = file_get_contents(resource_path('css/admin/screens/_packages.css'));

    expect($css)->toContain('.ag-preset-module-row .ag-package-mark {')
        ->toContain('width: 4.5rem;')
        ->toContain('height: 5.625rem;');
});

test('extension search uses a supported magnifier icon', function () {
    $source = file_get_contents(resource_path('views/components/ag/icon.blade.php'));
    expect($source)->toContain("'search' =>");
});

test('extension package fallback icon uses a supported puzzle glyph', function () {
    $source = file_get_contents(resource_path('views/components/ag/icon.blade.php'));
    expect($source)->toContain("'puzzle' =>");
});

test('package groups own headings without nesting cards inside a second panel', function () {
    $source = file_get_contents(resource_path('views/livewire/admin/packages/partials/package-group-grid.blade.php'));
    expect($source)->toContain('class="ag-package-section"')
        ->and($source)->not->toContain('class="admin-panel"');
});

test('extension cards retain lifecycle actions in a horizontal media layout', function () {
    $source = file_get_contents(resource_path('views/livewire/admin/packages/partials/extension-card.blade.php'));
    expect($source)->toContain('ag-package-card__media')
        ->and($source)->toContain('ag-package-card__body')
        ->and($source)->toContain('ag-package-card__controls')
        ->and($source)->toContain('aria-label="{{ __(\'admin.extensions.actions.settings\') }}"');

    foreach (['installFromMonorepo', 'install(', 'disable(', 'openSettings(', 'runHealth(', 'uninstallPackage(', 'enable(', 'updatePackage(', 'purgePackage('] as $action) {
        expect($source)->toContain($action);
    }
});

test('dashboard KPI icon tiles use a legible glyph without enlarging unrelated tiles', function () {
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $icons = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " ag-metric ")]//*[contains(concat(" ", normalize-space(@class), " "), " ag-icon-tile ")]/svg');

    expect($icons->length)->toBe(6);
    foreach ($icons as $icon) {
        expect($icon->getAttribute('width'))->toBe('24');
    }
});

test('dashboard KPI tiles keep a full contextual color in both themes', function () {
    $css = file_get_contents(resource_path('css/admin/screens/_dashboard.css'));
    expect($css)->toMatch('/\\.admin-dashboard \\.ag-metric \\.ag-icon-tile\\s*\\{[^}]*background: var\\(--ag-tile-color\\)/s');
});

test('dashboard columns share one bottom edge and the topbar content edges', function () {
    $dashboard = file_get_contents(resource_path('css/admin/screens/_dashboard.css'));
    $shell = file_get_contents(resource_path('css/admin/components/_admin-shell.css'));

    // Both columns grow into the shared grid row, whichever one is intrinsically taller.
    expect($dashboard)->toMatch('/\\.ag-dashboard-stack:last-child > \\.ag-dashboard-widget:last-child\\s*\\{[^}]*flex: 1/s')
        // The dashboard keeps the shell inline padding, so content starts under the breadcrumb.
        ->and($dashboard)->not->toMatch('/\\.admin-content:has\\(> \\.admin-dashboard\\)\\s*\\{[^}]*padding(-inline)?:[^;]*2\\.75rem/s')
        ->and($dashboard)->toMatch('/\\.admin-content:has\\(> \\.admin-dashboard\\)\\s*\\{[^}]*padding-block: var\\(--ag-space-7\\)/s')
        ->and($shell)->toMatch('/\\.admin-topbar \\{[^}]*padding: 0 var\\(--ag-space-6\\)/s')
        ->and($shell)->toMatch('/\\.admin-content \\{[^}]*padding: var\\(--ag-space-6\\)/s')
        // The account glyph, not its hit area, ends on the content edge.
        ->and($shell)->toMatch('/@media \\(min-width: 901px\\) \\{\\s*\\.admin-account-dropdown \\{[^}]*margin-inline-end: calc\\(\\(2\\.875rem - 1\\.375rem\\) \\/ -2\\)/s');
});

test('screen-reader-only tables sit inside a hidden block so their rows cannot stretch the page', function () {
    // A table ignores height: 1px and grows with its rows; a clipped block wrapper contains it.
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/<table\b[^>]*class="[^"]*\b(ag-)?visually-hidden\b/', (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([])
        ->and((string) file_get_contents(resource_path('views/livewire/admin/dashboard.blade.php')))
        ->toMatch('/<div class="visually-hidden">\s*<table id="dashboard-chart-data">/');
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

test('dashboard support empty state keeps a centered icon and real availability message', function () {
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();
    expect($html)->toContain('ag-dashboard-widget__empty')
        ->and($html)->toContain(__('admin.dashboard.support.empty_title'), false);
});

test('dashboard session heading matches the approved language in both locales', function () {
    expect(__('admin.dashboard.active_users.title', [], 'en'))->toBe('Current sessions')
        ->and(__('admin.dashboard.active_users.title', [], 'nl'))->toBe('Huidige sessies');
});

test('dashboard chart modes have distinct labels in both locales', function () {
    expect(__('admin.dashboard.charts.types.line', [], 'en'))->toBe('Line')
        ->and(__('admin.dashboard.charts.types.line', [], 'nl'))->toBe('Lijn')
        ->and(__('admin.dashboard.charts.types.bar', [], 'en'))->toBe('Bar')
        ->and(__('admin.dashboard.charts.types.bar', [], 'nl'))->toBe('Balk');
});

test('dashboard renders both revenue and orders as separate straight line or bar series', function () {
    $component = Livewire::actingAs($this->createStaff())->test(Dashboard::class);
    foreach (['line', 'bar'] as $selected) {
        $component->set('chartType', $selected);
        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $chart = (new DOMXPath($document))->query('//*[@data-chart-config]')->item(0);
        $config = json_decode($chart->getAttribute('data-chart-config'), true, flags: JSON_THROW_ON_ERROR);
        expect($config['type'])->toBe($selected)
            ->and(array_column($config['datasets'], 'type'))->toBe([$selected, $selected])
            ->and(array_column($config['datasets'], 'yAxisID'))->toBe(['y', 'y1'])
            ->and(array_column($config['datasets'], 'tension'))->toBe([0, 0])
            ->and(array_column($config['datasets'], 'fill'))->toBe([false, false]);
    }
});

test('dashboard line series use the same contrasting colors as the bar series', function () {
    $component = Livewire::actingAs($this->createStaff())->test(Dashboard::class);
    foreach (['line', 'bar'] as $type) {
        $component->set('chartType', $type);
        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $chart = (new DOMXPath($document))->query('//*[@data-chart-config]')->item(0);
        $datasets = json_decode($chart->getAttribute('data-chart-config'), true, flags: JSON_THROW_ON_ERROR)['datasets'];

        expect(array_column($datasets, 'borderColor'))->toBe([
            'var(--ag-color-chart-1)',
            'var(--ag-color-chart-4)',
        ]);
        foreach ($datasets as $dataset) {
            expect($dataset['borderColor'])->toBe($dataset['barBackgroundColor'])
                ->and($dataset['backgroundColor'])->toBe($dataset['barBackgroundColor'])
                ->and($dataset['pointBackgroundColor'])->toBe($dataset['barBackgroundColor'])
                ->and($dataset['pointHoverBackgroundColor'])->toBe($dataset['barBackgroundColor']);
        }
    }
});

test('dashboard chart exposes daily values in an accessible table without changing its canvas', function () {
    $staff = $this->createStaff();
    Order::factory()->create(['created_at' => now()]);
    $series = app(DashboardMetrics::class)->build('14');
    $document = new DOMDocument;
    $component = Livewire::actingAs($staff)->test(Dashboard::class);
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$component->html());
    $xpath = new DOMXPath($document);
    $table = $xpath->query('//table[@id="dashboard-chart-data"]')->item(0);

    expect($table)->not->toBeNull()
        ->and($table->parentNode->getAttribute('class'))->toContain('visually-hidden')
        ->and($xpath->query('.//caption', $table)->item(0)->textContent)->toContain(__('admin.dashboard.charts.overview'))
        ->and($xpath->query('.//tbody/tr', $table))->toHaveCount(14)
        ->and($xpath->query('//canvas[@x-ref="canvas"]'))->toHaveCount(1);

    $lastRow = $xpath->query('.//tbody/tr[last()]', $table)->item(0);
    expect(trim($xpath->query('./th', $lastRow)->item(0)->textContent))->toBe(now()->locale(app()->getLocale())->isoFormat('LL'))
        ->and(trim($xpath->query('./td[1]', $lastRow)->item(0)->textContent))->toBe(MoneyFormatter::format($series['revenueSeries']['values'][13], $series['revenueSeries']['currency']))
        ->and(trim($xpath->query('./td[2]', $lastRow)->item(0)->textContent))->toBe('1');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$component->set('chartRange', '90')->set('chartType', 'bar')->html());
    expect((new DOMXPath($document))->query('//table[@id="dashboard-chart-data"]/tbody/tr'))->toHaveCount(90);
});

test('dashboard chart data table localizes nonzero money and full dates', function () {
    $staff = $this->createStaff();
    $order = Order::factory()->create(['created_at' => now()]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Paid,
        'amount' => 123456,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);

    foreach (['en' => '1,234.56', 'nl' => '1.234,56'] as $locale => $amount) {
        app()->setLocale($locale);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.Livewire::actingAs($staff)->test(Dashboard::class)->html());
        $xpath = new DOMXPath($document);
        $row = $xpath->query('//table[@id="dashboard-chart-data"]/tbody/tr[last()]')->item(0);
        expect($row)->not->toBeNull()
            ->and(trim($xpath->query('./th', $row)->item(0)->textContent))->toBe(now()->locale($locale)->isoFormat('LL'))
            ->and(trim($xpath->query('./td[1]', $row)->item(0)->textContent))->toContain($amount)
            ->and(trim($xpath->query('./td[2]', $row)->item(0)->textContent))->toBe('1');
    }
});

test('dashboard chart data table distinguishes dates across a year boundary', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00:00'));
    $staff = $this->createStaff();
    Order::factory()->create(['created_at' => now()]);

    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.Livewire::actingAs($staff)->test(Dashboard::class)->html());
        $xpath = new DOMXPath($document);
        $dates = [];
        foreach ($xpath->query('//table[@id="dashboard-chart-data"]/tbody/tr/th') as $heading) {
            $dates[] = trim($heading->textContent);
        }

        expect($dates)->toHaveCount(14)
            ->toContain(CarbonImmutable::parse('2026-12-31')->locale($locale)->isoFormat('LL'))
            ->toContain(CarbonImmutable::parse('2027-01-01')->locale($locale)->isoFormat('LL'));
    }
});

test('dashboard chart disables animations during Livewire and theme replacement', function () {
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $chart = (new DOMXPath($document))->query('//*[@data-chart-config]')->item(0);
    $config = json_decode($chart->getAttribute('data-chart-config'), true, flags: JSON_THROW_ON_ERROR);
    $script = file_get_contents(resource_path('js/admin/chart.js'));
    expect($config['animate'] ?? null)->toBeFalse()
        ->and($script)->toContain('config.animate === false');
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

test('admin navigation sections expand related pages and fulfillment icons are distinct', function () {
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
        ->assertSee('admin-nav__trigger', false)
        ->assertSee('aria-controls="nav-group-panel-', false)
        ->getContent();

    expect($html)->toContain('agovena.admin.nav.group.v8.')
        ->and($html)->toContain('x-data="agAdminNavGroup"')
        ->and($html)->toContain('data-open="false"')
        ->and($html)->not->toContain('agovena.admin.nav.v6.');
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

<?php

declare(strict_types=1);

use App\Agovena\Admin\GettingStartedChecklist;
use App\Agovena\Admin\GettingStartedItem;
use App\Livewire\Admin\Dashboard;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('dashboard shows a dismissible getting started checklist', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertSee(__('admin.dashboard.getting_started.title'))
        ->assertSee('ag-checklist__toggle', false)
        ->assertSee('x-data="{ open: false }"', false)
        ->assertSee('width="20"', false)
        ->assertSee(__('admin.dashboard.getting_started.product'))
        ->call('dismissGettingStarted')
        ->assertDontSee(__('admin.dashboard.getting_started.title'));

    expect(app(GettingStartedChecklist::class)->dismissed())->toBeTrue();
});

test('dashboard summary shows progress without a duplicate checklist status', function () {
    $item = new GettingStartedItem(
        id: 'product',
        labelKey: 'admin.dashboard.getting_started.product',
        href: '/admin/products/create',
        done: true,
    );

    $html = view('livewire.admin.dashboard', [
        'gettingStarted' => [$item],
        'metrics' => [],
        'revenueSeries' => ['labels' => [], 'values' => []],
        'orderSeries' => ['labels' => [], 'values' => []],
        'chartRange' => '14',
        'chartType' => 'line',
        'chartRanges' => [],
        'chartTypes' => [],
        'recentOrders' => collect(),
        'attentionItems' => [],
    ])->render();

    expect($html)
        ->not->toContain('ag-checklist__summary-status')
        ->toContain(__('admin.dashboard.getting_started.progress', ['done' => 1, 'total' => 1]));
});

test('getting started hides after the remaining store setup items are done', function () {
    $this->createStaff();
    Product::factory()->active()->create();
    app(GettingStartedChecklist::class)->dismiss();

    expect(app(GettingStartedChecklist::class)->items())->toBe([]);
});

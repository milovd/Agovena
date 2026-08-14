<?php

declare(strict_types=1);

use App\Agovena\Admin\GettingStartedChecklist;
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
        ->assertSee(__('admin.dashboard.getting_started.product'))
        ->call('dismissGettingStarted')
        ->assertDontSee(__('admin.dashboard.getting_started.title'));

    expect(app(GettingStartedChecklist::class)->dismissed())->toBeTrue();
});

test('getting started hides after the remaining store setup items are done', function () {
    $this->createStaff();
    Product::factory()->active()->create();
    app(GettingStartedChecklist::class)->dismiss();

    expect(app(GettingStartedChecklist::class)->items())->toBe([]);
});

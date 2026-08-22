<?php

use App\Agovena\Settings\SettingsRepository;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Categories\Index;
use App\Livewire\Admin\Settings\EditGroup;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin shell shows grouped catalog, sales, and system navigation', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Overview', false)
        ->assertSee(__('admin.nav_groups.catalog'), false)
        ->assertSee(__('admin.nav_groups.sales'), false)
        ->assertSee(__('admin.nav_groups.customers'), false)
        ->assertSee('Categories', false)
        ->assertSee('System', false)
        ->assertSee('Settings', false)
        ->assertSee('Currencies', false)
        ->assertSee(__('admin.view_storefront'), false)
        ->assertDontSee('>General</a>', false)
        ->assertDontSee('Configuration', false);
});

test('settings hub lists registered groups from the admin registrar', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Branding', false)
        ->assertSee('Store', false)
        ->assertSee('/admin/settings/general', false);
});

test('guest is redirected from admin', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('navigation hides settings without permission', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('>General</a>', false)
        ->assertSee('Dashboard', false);
});

test('dashboard shows real product and order counts', function () {
    $staff = $this->createStaff();
    Product::factory()->create(['status' => ProductStatus::Active]);
    Product::factory()->create(['status' => ProductStatus::Draft]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Paid,
        'amount' => 2500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('2', false)
        ->assertSee('1 active', false)
        ->assertSee($order->number, false);
});

test('settings persist via repository and admin screen', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(EditGroup::class, ['group' => 'general'])
        ->set('values.site_name', 'Acme Commerce')
        ->set('values.locale', 'en')
        ->set('values.timezone', 'UTC')
        ->set('values.base_currency', 'EUR')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('general', 'site_name'))->toBe('Acme Commerce');

    $this->actingAs($staff)
        ->get('/')
        ->assertOk()
        ->assertSee('Acme Commerce', false);
});

test('staff without settings update cannot save', function () {
    $staff = $this->createStaff([], ['settings.view', 'dashboard.view']);

    Livewire::actingAs($staff)
        ->test(EditGroup::class, ['group' => 'general'])
        ->set('values.site_name', 'Nope')
        ->call('save')
        ->assertForbidden();
});

test('owner can create a category', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('create')
        ->set('name', 'Hosting')
        ->set('slug', 'hosting')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['slug' => 'hosting', 'name' => 'Hosting']);
});

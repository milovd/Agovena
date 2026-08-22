<?php

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('storefront home renders catalog theme', function () {
    $this->get('/')->assertOk()->assertSee('Featured products', false);
});

test('installer redirects when already installed', function () {
    $this->get('/install')
        ->assertRedirect(route('admin.dashboard'));
});

test('admin registrar is bound', function () {
    $admin = app(AdminRegistrar::class);

    expect($admin)->toBeInstanceOf(InMemoryAdminRegistrar::class)
        ->and($admin->navigationItems())->not->toBeEmpty();
});

test('authenticated owner sees admin dashboard', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard', false)
        ->assertSee('Products', false)
        ->assertSee('System', false)
        ->assertSee(__('admin.exit_admin'), false)
        ->assertDontSee(__('admin.view_storefront'), false);
});

test('admin registrar exposes settings and widgets', function () {
    /** @var InMemoryAdminRegistrar $admin */
    $admin = app(AdminRegistrar::class);

    expect($admin->settingsGroups())->not->toBeEmpty()
        ->and($admin->widgets())->not->toBeEmpty()
        ->and($admin->permissions())->toHaveKey('settings.update')
        ->and($admin->permissions())->toHaveKey('notifications.manage')
        ->and($admin->permissions())->toHaveKey('jobs.view')
        ->and($admin->permissions())->toHaveKey('invoices.credit')
        ->and($admin->permissions())->toHaveKey('payments.refund')
        ->and($admin->permissions())->toHaveKey('orders.cancel');
});

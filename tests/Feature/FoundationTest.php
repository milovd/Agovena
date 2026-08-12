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
        ->assertRedirect(route('admin.login'));
});

test('admin registrar is bound', function () {
    $admin = app(AdminRegistrar::class);

    expect($admin)->toBeInstanceOf(InMemoryAdminRegistrar::class)
        ->and($admin->navigationItems())->not->toBeEmpty();
});

test('authenticated owner sees admin dashboard', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff, 'staff')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Commerce overview', false)
        ->assertSee('Products', false)
        ->assertSee('Configuration', false);
});

test('admin registrar exposes settings and widgets', function () {
    /** @var InMemoryAdminRegistrar $admin */
    $admin = app(AdminRegistrar::class);

    expect($admin->settingsGroups())->not->toBeEmpty()
        ->and($admin->widgets())->not->toBeEmpty()
        ->and($admin->permissions())->toHaveKey('settings.update');
});

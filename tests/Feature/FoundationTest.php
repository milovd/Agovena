<?php

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('storefront home renders catalog theme', function () {
    $this->get('/')->assertOk()->assertSee('Catalog', false);
});

test('installer welcome renders', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee('Agovena installer', false);
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
        ->assertSee('Welcome to Agovena Admin', false);
});

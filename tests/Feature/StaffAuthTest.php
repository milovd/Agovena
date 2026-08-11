<?php

use App\Livewire\Admin\Auth\Login;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('guest is redirected from admin to login', function () {
    $this->get('/admin')->assertRedirect(route('admin.login'));
});

test('guest cannot access admin products', function () {
    $this->get('/admin/products')->assertRedirect(route('admin.login'));
});

test('staff can sign in and reach dashboard', function () {
    $staff = $this->createStaff([
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'owner@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($staff, 'staff');
});

test('staff without dashboard permission is forbidden', function () {
    $staff = $this->createStaff([], ['products.view']);

    $this->actingAs($staff, 'staff')
        ->get('/admin')
        ->assertForbidden();
});

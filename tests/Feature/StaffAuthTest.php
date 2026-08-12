<?php

use App\Livewire\Customer\Auth\Login;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('guest is redirected from admin to the unified login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('guest cannot access admin products', function () {
    $this->get('/admin/products')->assertRedirect(route('login'));
});

test('legacy admin login url redirects to the unified login', function () {
    $this->get('/admin/login')->assertRedirect(route('login'));
});

test('user can sign in and reach admin dashboard when authorized', function () {
    $staff = $this->createStaff([
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    $this->get('/admin')->assertRedirect(route('login'));

    Livewire::test(Login::class)
        ->set('email', 'owner@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($staff);
});

test('staff without dashboard permission is forbidden', function () {
    $staff = $this->createStaff([], ['products.view']);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertForbidden();
});

test('authenticated customer without admin permission is forbidden from admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('remember me on the unified guard still authenticates after login', function () {
    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'remember@example.com')
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->remember_token)->not->toBeNull();
});

test('auth pages use the storefront form design system', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('store-auth', false)
        ->assertSee('store-btn--primary', false);

    $this->get('/register')
        ->assertOk()
        ->assertSee('store-auth', false)
        ->assertSee('store-btn--primary', false);

    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('store-auth', false)
        ->assertSee('store-btn--primary', false);
});

<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('logged out storefront header shows styled login and register actions', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.login'), false)
        ->assertSee(__('storefront.nav.register'), false)
        ->assertSee('store-header__auth-link', false)
        ->assertSee('store-header__auth-register', false)
        ->assertSee('store-drawer__auth', false)
        ->assertDontSee('id="store-account-menu-button"', false);
});

test('logged in customer sees account menu without admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('id="store-account-menu-button"', false)
        ->assertSee('store-header__account-trigger', false)
        ->assertSee(__('storefront.nav.dashboard'), false)
        ->assertSee(__('storefront.nav.logout'), false)
        ->assertSee('store-header__account-item--danger', false)
        ->assertDontSee(__('storefront.nav.admin'), false)
        ->assertDontSee(__('storefront.nav.login'), false);
});

test('logged in admin sees account menu with admin link', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/')
        ->assertOk()
        ->assertSee('id="store-account-menu-button"', false)
        ->assertSee(__('storefront.nav.admin'), false)
        ->assertSee(route('admin.dashboard'), false);
});

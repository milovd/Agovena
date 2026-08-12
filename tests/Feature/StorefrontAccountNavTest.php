<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('logged out storefront header shows login and register instead of an account icon', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.login'), false)
        ->assertSee(__('storefront.nav.register'), false)
        ->assertDontSee('id="store-account-menu-button"', false);
});

test('logged in customer sees account menu without admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('id="store-account-menu-button"', false)
        ->assertSee(__('storefront.nav.dashboard'), false)
        ->assertSee(__('storefront.nav.logout'), false)
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
